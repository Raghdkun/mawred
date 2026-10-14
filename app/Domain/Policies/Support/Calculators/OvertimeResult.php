<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support\Calculators;

/**
 * Overtime minutes per multiplier bucket (`workday`, `weekend`, `holiday`, `night`), feeding the
 * payroll-input export (D7).
 */
final readonly class OvertimeResult
{
    /**
     * @param  array<string, array{multiplier: float, minutes: int}>  $buckets
     */
    public function __construct(public array $buckets = []) {}

    public function minutes(): int
    {
        return array_sum(array_column($this->buckets, 'minutes'));
    }

    /** Minutes weighted by their multipliers (1.5 × 60 min = 90). */
    public function weightedMinutes(): float
    {
        return array_sum(array_map(fn (array $bucket): float => $bucket['minutes'] * $bucket['multiplier'], $this->buckets));
    }
}
