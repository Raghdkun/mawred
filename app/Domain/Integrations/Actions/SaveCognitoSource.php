<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Forms\Models\Form;
use App\Domain\Integrations\Contracts\CognitoApplications;
use App\Domain\Integrations\Data\CognitoSourceData;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\Integrations\Support\Connections;
use App\Domain\Requests\Models\RequestType;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Creates or edits a Cognito source (ADR-0015). A new source gets its secret token, returned
 * once so the admin can copy the three URLs; Mawred keeps only its hash. The linked request
 * type becomes Cognito-backed (D60). A source that feeds a job posting (D81) can't also feed a
 * request type.
 */
final readonly class SaveCognitoSource
{
    public function __construct(private ConnectionInterface $db, private CognitoApplications $applications) {}

    /**
     * @return array{0: CognitoSource, 1: string|null} the source and, when new, its token
     */
    public function handle(Actor $actor, CognitoSourceData $data, ?CognitoSource $source = null): array
    {
        return $this->db->transaction(function () use ($actor, $data, $source): array {
            $token = null;
            $source ??= new CognitoSource;
            $previousType = $source->request_type_id;

            if (! $source->exists) {
                $token = self::newToken();
                $source->forceFill(['token_hash' => hash('sha256', $token), 'token_last4' => substr($token, -4), 'created_by' => $actor->user?->id]);
            }

            $type = $data->requestTypeId === null ? null : RequestType::query()->where('ulid', $data->requestTypeId)->first();

            if ($type instanceof RequestType && $source->exists && $this->applications->form($source) instanceof Form) {
                $message = __('This source feeds a job posting. Unlink it from the posting first.');

                throw new DomainRuleViolation('integrations.cognito_feeds_posting', $message, ['requestTypeId' => [$message]]);
            }

            $source->fill([
                'name' => trim($data->name),
                'request_type_id' => $type?->id,
                'form_url' => $data->formUrl,
                'is_active' => $data->isActive,
                'mapping' => [
                    'fields' => array_map(fn (array $row): array => ['from' => trim($row['from']), 'to' => trim($row['to'])], $data->fields),
                    'requester' => ['by' => $data->requesterBy, 'path' => trim($data->requesterPath ?? '')],
                    'identity' => array_filter(['entry' => $data->entryPath, 'version' => $data->versionPath]),
                    'prefill' => array_filter($data->prefill, fn (?string $path): bool => $path !== null && $path !== ''),
                ],
            ])->save();

            if ($type instanceof RequestType) {
                $type->forceFill(['source' => 'cognito'])->save();
            }

            if ($previousType !== null && $previousType !== $type?->id && ! CognitoSource::query()->where('request_type_id', $previousType)->exists()) {
                RequestType::query()->whereKey($previousType)->update(['source' => 'native']);
            }

            $connection = Connections::findOrNew(IntegrationType::Cognito);

            if (! $connection->exists || $connection->healthStatus() === IntegrationStatus::NotConfigured) {
                $connection->forceFill(['status' => IntegrationStatus::Unverified->value])->save();
            }

            return [$source, $token];
        });
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }
}
