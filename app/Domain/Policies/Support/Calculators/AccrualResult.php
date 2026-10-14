<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support\Calculators;

final readonly class AccrualResult
{
    public function __construct(
        /** Earned in the leave year up to the as-of date, after cumulative rounding. */
        public float $accrued,
        /** The yearly entitlement at the as-of date (tenure steps applied). */
        public float $annualEntitlement,
        /** First day accrual counts (hire date plus the waiting period). */
        public string $accruesFrom,
    ) {}
}
