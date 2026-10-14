<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use Illuminate\Database\ConnectionInterface;

/**
 * Append-only delivery log (partitioned by month). The idempotency key makes a retried cause
 * (re-run job, duplicate event) a no-op per person and channel.
 */
final readonly class DeliveryLog
{
    public function __construct(private ConnectionInterface $db) {}

    public function seen(string $key): bool
    {
        return $this->db->table('notification_deliveries')->where('idempotency_key', $key)->where('created_at', '>=', now()->subDays(45))->exists();
    }

    public function record(?int $userId, string $event, string $category, string $channel, string $status, ?string $key, ?string $error = null): void
    {
        $this->db->table('notification_deliveries')->insert([
            'user_id' => $userId,
            'event' => $event,
            'category' => $category,
            'channel' => $channel,
            'status' => $status,
            'idempotency_key' => $key === null ? null : mb_substr($key, 0, 120),
            'error' => $error,
            'created_at' => now(),
        ]);
    }
}
