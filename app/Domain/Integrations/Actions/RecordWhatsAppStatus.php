<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Models\WhatsAppMessage;

/** Delivery receipts from Meta (sent, delivered, read, failed) on the message log. */
final readonly class RecordWhatsAppStatus
{
    public function handle(string $providerId, string $status, ?string $errorCode = null, ?string $error = null): void
    {
        $message = WhatsAppMessage::query()->where('provider_id', $providerId)->where('created_at', '>=', now()->subDays(30))->latest('id')->first();

        if (! $message instanceof WhatsAppMessage || ! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $order = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

        if ($order[$status] < ($order[$message->status] ?? 0)) {
            return;
        }

        $message->forceFill([
            'status' => $status,
            'delivered_at' => $status === 'delivered' ? now() : $message->delivered_at,
            'read_at' => $status === 'read' ? now() : $message->read_at,
            'error_code' => $errorCode ?? $message->error_code,
            'error' => $error ?? $message->error,
        ])->save();
    }
}
