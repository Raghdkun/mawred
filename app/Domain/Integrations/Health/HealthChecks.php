<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Health;

use App\Domain\Integrations\Contracts\HealthCheck;
use App\Domain\Integrations\Enums\IntegrationType;

/** Registry of health checks by connection type (drivers register theirs). */
final class HealthChecks
{
    /** @var array<string, HealthCheck> */
    private array $checks = [];

    public function register(HealthCheck $check): void
    {
        $this->checks[$check->type()->value] = $check;
    }

    public function for(IntegrationType $type): ?HealthCheck
    {
        return $this->checks[$type->value] ?? null;
    }
}
