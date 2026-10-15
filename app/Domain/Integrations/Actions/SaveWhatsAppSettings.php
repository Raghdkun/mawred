<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\Connections;
use App\Support\Actors\Actor;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Stores a WhatsApp driver's settings in the vault (ADR-0015). Secrets are only replaced when a
 * new value is given and are never sent back; Mawred generates the webhook verify token (Official)
 * and the webhook signing key (WAHA). Credential changes are audited without values.
 */
final readonly class SaveWhatsAppSettings
{
    /**
     * @param  array<string, string|null>  $config  plain settings
     * @param  array<string, string|null>  $secrets  new secret values (empty = keep)
     */
    public function handle(Actor $actor, IntegrationType $type, array $config, array $secrets): IntegrationConnection
    {
        if (! in_array($type, [IntegrationType::WhatsAppOfficial, IntegrationType::WhatsAppWaha], true)) {
            throw new InvalidArgumentException('Not a WhatsApp driver.');
        }

        $connection = Connections::findOrNew($type);
        $credentials = $connection->credentials ?? [];
        $changed = [];

        foreach ($secrets as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                $credentials[$key] = trim($value);
                $changed[] = $key;
            }
        }

        $generated = $type === IntegrationType::WhatsAppOfficial ? 'verify_token' : 'webhook_key';
        $credentials[$generated] ??= Str::random(40);

        $connection->forceFill([
            'config' => array_map(fn (?string $value): ?string => $value === null ? null : trim($value), $config),
            'credentials' => $credentials,
            'status' => $connection->healthStatus() === IntegrationStatus::NotConfigured || $changed !== [] ? IntegrationStatus::Unverified->value : $connection->status,
        ])->save();

        if ($changed !== []) {
            activity('integrations')->causedBy($actor->user)->performedOn($connection)->event('credentials.changed')->withProperties(['keys' => $changed])->log('WhatsApp credentials changed');
        }

        return $connection;
    }
}
