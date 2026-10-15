<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WhatsApp;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Contracts\WhatsAppDriver;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppMessage;
use App\Domain\Integrations\Support\Connections;
use App\Support\Crypto\BlindIndex;

/**
 * Sends through a chosen driver and writes every attempt to the message log (ADR-0015).
 * Disconnected or unconfigured drivers are skipped so the caller falls back to the next step.
 */
final readonly class WhatsAppGateway
{
    public function __construct(private OfficialDriver $official, private WahaDriver $waha) {}

    public function driver(IntegrationType $type): ?WhatsAppDriver
    {
        return match ($type) {
            IntegrationType::WhatsAppOfficial => $this->official,
            IntegrationType::WhatsAppWaha => $this->waha,
            default => null,
        };
    }

    public static function usable(IntegrationType $type): ?IntegrationConnection
    {
        $connection = Connections::find($type);

        return $connection instanceof IntegrationConnection && ! in_array($connection->healthStatus(), [IntegrationStatus::NotConfigured, IntegrationStatus::Disconnected], true)
            ? $connection
            : null;
    }

    public function send(IntegrationType $type, ?User $user, string $purpose, OutgoingMessage $message): SendResult
    {
        $driver = $this->driver($type);
        $connection = self::usable($type);

        if (! $driver instanceof WhatsAppDriver || ! $connection instanceof IntegrationConnection) {
            return SendResult::failed(__('This WhatsApp connection is not available.'), 'unavailable');
        }

        $result = $driver->send($connection, $message);

        WhatsAppMessage::query()->create([
            'user_id' => $user?->id,
            'driver' => $type->value,
            'purpose' => mb_substr($purpose, 0, 48),
            'phone_last4' => mb_substr($message->digits(), -4),
            'phone_hash' => BlindIndex::of($message->to, 'whatsapp.phone'),
            'template' => $result->template,
            'status' => $result->sent ? 'sent' : 'failed',
            'provider_id' => $result->providerId,
            'error_code' => $result->errorCode,
            'error' => $result->error,
        ]);

        return $result;
    }
}
