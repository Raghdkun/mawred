<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Health;

use App\Domain\Integrations\Contracts\HealthCheck;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\WhatsApp\WahaDriver;
use Throwable;

/** Asks the sidecar for the session status: WORKING is healthy, pairing is degraded. */
final readonly class WahaHealthCheck implements HealthCheck
{
    public function __construct(private WahaDriver $driver) {}

    public function type(): IntegrationType
    {
        return IntegrationType::WhatsAppWaha;
    }

    public function check(IntegrationConnection $connection): HealthResult
    {
        try {
            $status = $this->driver->status($connection);
        } catch (Throwable $e) {
            return HealthResult::disconnected(mb_substr($e->getMessage(), 0, 500));
        }

        return match ($status) {
            'WORKING' => HealthResult::healthy(),
            'STARTING', 'SCAN_QR_CODE' => HealthResult::degraded(__('The WhatsApp Web session is waiting to be paired.')),
            default => HealthResult::disconnected(__('The WhatsApp Web session is :status.', ['status' => mb_strtolower((string) $status)])),
        };
    }
}
