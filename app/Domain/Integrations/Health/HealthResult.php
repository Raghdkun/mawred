<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Health;

use App\Domain\Integrations\Enums\IntegrationStatus;

final readonly class HealthResult
{
    public function __construct(public IntegrationStatus $status, public ?string $error = null) {}

    public static function healthy(): self
    {
        return new self(IntegrationStatus::Healthy);
    }

    public static function degraded(string $error): self
    {
        return new self(IntegrationStatus::Degraded, $error);
    }

    public static function disconnected(string $error): self
    {
        return new self(IntegrationStatus::Disconnected, $error);
    }
}
