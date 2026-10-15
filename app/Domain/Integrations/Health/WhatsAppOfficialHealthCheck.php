<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Health;

use App\Domain\Integrations\Contracts\HealthCheck;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\WhatsApp\OfficialDriver;
use Throwable;

/** Reads the phone number's profile with the stored token (nothing is sent). */
final readonly class WhatsAppOfficialHealthCheck implements HealthCheck
{
    public function __construct(private OfficialDriver $driver) {}

    public function type(): IntegrationType
    {
        return IntegrationType::WhatsAppOfficial;
    }

    public function check(IntegrationConnection $connection): HealthResult
    {
        try {
            $response = $this->driver->probe($connection);
        } catch (Throwable $e) {
            return HealthResult::disconnected(mb_substr($e->getMessage(), 0, 500));
        }

        if (! $response->successful()) {
            return HealthResult::disconnected(OfficialDriver::error($response));
        }

        $quality = $response->json('quality_rating');

        return $quality === 'RED' ? HealthResult::degraded(__('Meta rates this number’s quality as low; messages may be limited.')) : HealthResult::healthy();
    }
}
