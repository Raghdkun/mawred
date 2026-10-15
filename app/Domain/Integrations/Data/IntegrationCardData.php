<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Data;

use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One card in the integrations hub (handoff 41). Credentials only ever appear masked. */
#[TypeScript]
final class IntegrationCardData extends Data
{
    /**
     * @param  array<string, string|null>  $details  label key => display value
     */
    public function __construct(
        public string $type,
        public string $status,
        public ?string $checkedAt,
        public ?string $lastSuccessAt,
        public ?string $lastFailureAt,
        public ?string $lastError,
        public array $details,
    ) {}

    /**
     * @param  array<string, string|null>  $details
     */
    public static function for(IntegrationType $type, ?IntegrationConnection $connection, array $details = []): self
    {
        return new self(
            type: $type->value,
            status: $connection?->healthStatus()->value ?? IntegrationStatus::NotConfigured->value,
            checkedAt: $connection?->health_checked_at?->toIso8601String(),
            lastSuccessAt: $connection?->last_success_at?->toIso8601String(),
            lastFailureAt: $connection?->last_failure_at?->toIso8601String(),
            lastError: $connection?->last_error,
            details: $details,
        );
    }
}
