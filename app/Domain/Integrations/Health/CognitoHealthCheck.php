<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Health;

use App\Domain\Integrations\Contracts\HealthCheck;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\Integrations\Models\IntegrationConnection;

/**
 * Cognito calls us, so health comes from what arrived: degraded when at least three of the
 * last day's entries failed and they are over a fifth of them (ADR-0015 failure-rate spike).
 */
final class CognitoHealthCheck implements HealthCheck
{
    public function type(): IntegrationType
    {
        return IntegrationType::Cognito;
    }

    public function check(IntegrationConnection $connection): HealthResult
    {
        if (! CognitoSource::query()->where('is_active', true)->exists()) {
            return HealthResult::degraded(__('No active Cognito sources.'));
        }

        $recent = CognitoReceipt::query()->where('created_at', '>=', now()->subDay());
        $total = (clone $recent)->count();
        $failed = (clone $recent)->where('status', ReceiptStatus::Failed->value)->count();

        if ($failed >= 3 && $failed / max(1, $total) > 0.2) {
            return HealthResult::degraded(__(':failed of :total entries failed in the last 24 hours.', ['failed' => $failed, 'total' => $total]));
        }

        return HealthResult::healthy();
    }
}
