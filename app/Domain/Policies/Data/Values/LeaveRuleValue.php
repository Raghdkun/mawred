<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rules for one leave type: entitlement, accrual, pro-rata, rounding, cap, carry-over, expiry,
 * waiting period and tenure steps. Composed, typed components; never a formula.
 */
#[TypeScript]
final class LeaveRuleValue extends Data
{
    /**
     * @param  list<array{afterYears: int, entitlement: float}>  $tenureSteps
     */
    public function __construct(
        public float $entitlement,
        public string $accrual = 'monthly',
        public string $proRata = 'daily',
        public string $rounding = 'half',
        public ?float $maxBalance = null,
        public ?float $carryOverMax = 0,
        public ?int $carryOverExpiresMonths = null,
        public int $eligibleAfterDays = 0,
        public array $tenureSteps = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'entitlement' => ['required', 'numeric', 'between:0,3650'],
            'accrual' => ['required', Rule::in(['monthly', 'yearly_upfront', 'none'])],
            'proRata' => ['required', Rule::in(['none', 'daily', 'monthly'])],
            'rounding' => ['required', Rule::in(['none', 'half', 'whole'])],
            'maxBalance' => ['nullable', 'numeric', 'between:0,3650'],
            'carryOverMax' => ['nullable', 'numeric', 'between:0,3650'],
            'carryOverExpiresMonths' => ['nullable', 'integer', 'between:1,60'],
            'eligibleAfterDays' => ['integer', 'between:0,3650'],
            'tenureSteps' => ['array', 'max:10'],
            'tenureSteps.*.afterYears' => ['required', 'integer', 'between:1,60'],
            'tenureSteps.*.entitlement' => ['required', 'numeric', 'between:0,3650'],
        ];
    }

    /** Entitlement for an employee with this many completed years of service. */
    public function entitlementAfter(int $years): float
    {
        $entitlement = $this->entitlement;
        $steps = $this->tenureSteps;
        usort($steps, fn (array $a, array $b): int => $a['afterYears'] <=> $b['afterYears']);

        foreach ($steps as $step) {
            if ($years >= $step['afterYears']) {
                $entitlement = (float) $step['entitlement'];
            }
        }

        return $entitlement;
    }
}
