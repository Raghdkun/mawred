<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Contracts\HealthCheck;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Events\IntegrationHealthChanged;
use App\Domain\Integrations\Health\HealthChecks;
use App\Domain\Integrations\Models\IntegrationConnection;

/**
 * Runs a connection's health check and records the result (ADR-0015 hub). A change of badge
 * raises IntegrationHealthChanged so the integration admins hear about it.
 */
final readonly class CheckIntegrationHealth
{
    public function __construct(private HealthChecks $checks) {}

    public function handle(IntegrationConnection $connection): IntegrationStatus
    {
        $check = $this->checks->for(IntegrationType::from($connection->type));
        $from = $connection->healthStatus();

        if (! $check instanceof HealthCheck || $from === IntegrationStatus::NotConfigured) {
            return $from;
        }

        $result = $check->check($connection);
        $connection->forceFill([
            'status' => $result->status->value,
            'health_checked_at' => now(),
            ...($result->status === IntegrationStatus::Healthy
                ? ['last_success_at' => now(), 'last_error' => null]
                : ['last_failure_at' => now(), 'last_error' => $result->error]),
        ])->save();

        if ($from !== $result->status && ($from !== IntegrationStatus::Unverified || $result->status !== IntegrationStatus::Healthy)) {
            event(new IntegrationHealthChanged($connection, $from, $result->status));
        }

        return $result->status;
    }
}
