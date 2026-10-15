<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Cognito\CognitoMapping;
use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Jobs\ProcessCognitoReceiptJob;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use Illuminate\Support\Facades\Cache;

/**
 * Stores what Cognito posted and queues it (ADR-0015): the endpoint must answer quickly because
 * Cognito retries any failure for up to 72 hours. The idempotency key is source + entry id +
 * event + entry version, or a hash of the payload when those are missing, so a retried post is
 * kept but not processed twice, while a genuine second update is.
 */
final readonly class ReceiveCognitoEntry
{
    /**
     * @param  'submit'|'update'|'delete'  $event
     * @param  array<string, mixed>  $payload
     */
    public function handle(CognitoSource $source, string $event, array $payload): CognitoReceipt
    {
        $mapping = CognitoMapping::of($source);
        $entry = CognitoMapping::scalar($payload, $mapping->entryPath);
        $version = CognitoMapping::scalar($payload, $mapping->versionPath);
        $key = $entry !== null && ($version !== null || $event === 'delete')
            ? "cognito:{$source->id}:{$entry}:{$event}:".($version ?? '-')
            : "cognito:{$source->id}:{$event}:".hash('sha256', (string) json_encode($payload));

        $lock = Cache::lock('cognito-receipt:'.hash('sha256', $key), 10);
        $lock->block(5);

        try {
            $duplicate = CognitoReceipt::query()->where('idempotency_key', $key)->where('created_at', '>=', now()->subDays(30))
                ->whereNotIn('status', [ReceiptStatus::Duplicate->value])->exists();

            $receipt = CognitoReceipt::query()->create([
                'cognito_source_id' => $source->id,
                'event' => $event,
                'entry_id' => $entry === null ? null : mb_substr($entry, 0, 64),
                'entry_version' => $version === null ? null : mb_substr($version, 0, 32),
                'idempotency_key' => mb_substr($key, 0, 160),
                'payload' => $payload,
                'status' => $duplicate ? ReceiptStatus::Duplicate : ReceiptStatus::Received,
            ]);
        } finally {
            $lock->release();
        }

        if (! $duplicate) {
            dispatch(new ProcessCognitoReceiptJob($receipt->id));
        }

        return $receipt;
    }
}
