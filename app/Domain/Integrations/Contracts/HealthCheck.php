<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Health\HealthResult;
use App\Domain\Integrations\Models\IntegrationConnection;

/** Probes one kind of connection without side effects (no messages sent). */
interface HealthCheck
{
    public function type(): IntegrationType;

    public function check(IntegrationConnection $connection): HealthResult;
}
