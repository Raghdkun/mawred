<?php

declare(strict_types=1);

use App\Domain\Policies\Data\Values\LeaveRuleValue;
use App\Domain\Policies\Support\Calculators\AccrualCalculator;

function annual(array $overrides = []): LeaveRuleValue
{
    return new LeaveRuleValue(...[
        'entitlement' => 21.0, 'accrual' => 'monthly', 'proRata' => 'daily', 'rounding' => 'half',
        'carryOverMax' => 5.0, 'carryOverExpiresMonths' => 3, ...$overrides,
    ]);
}

it('credits a twelfth at each month end, rounding the running total down', function (string $asOf, float $expected): void {
    expect(AccrualCalculator::accrued(annual(), '2023-01-01', '2026-01-01', $asOf)->accrued)->toBe($expected);
})->with([
    'before the first month end' => ['2026-01-30', 0.0],
    'first month end' => ['2026-01-31', 1.5],
    'mid October' => ['2026-10-15', 15.5],
    'year end' => ['2026-12-31', 21.0],
]);

it('rounds cumulatively, so whole-day rounding still reaches the full year', function (): void {
    $rule = annual(['rounding' => 'whole']);

    expect(AccrualCalculator::accrued($rule, '2023-01-01', '2026-01-01', '2026-02-28')->accrued)->toBe(3.0)
        ->and(AccrualCalculator::accrued($rule, '2023-01-01', '2026-01-01', '2026-12-31')->accrued)->toBe(21.0);
});

it('pro-rates a mid-month start', function (string $proRata, string $hired, float $expected): void {
    expect(AccrualCalculator::accrued(annual(['proRata' => $proRata]), $hired, '2026-01-01', '2026-12-31')->accrued)->toBe($expected);
})->with([
    'by days served' => ['daily', '2026-03-16', 16.5],
    'half a month or more counts' => ['monthly', '2026-03-16', 17.5],
    'less than half does not' => ['monthly', '2026-03-17', 15.5],
    'no pro-rata' => ['none', '2026-03-31', 17.5],
]);

it('starts accruing after the waiting period', function (): void {
    $rule = annual(['eligibleAfterDays' => 90]);
    $result = AccrualCalculator::accrued($rule, '2026-01-01', '2026-01-01', '2026-12-31');

    expect($result->accruesFrom)->toBe('2026-04-01')
        ->and($result->accrued)->toBe(15.5)
        ->and(AccrualCalculator::accrued($rule, '2026-01-01', '2026-01-01', '2026-03-31')->accrued)->toBe(0.0);
});

it('applies tenure steps from the month the years are completed', function (): void {
    $rule = annual(['tenureSteps' => [['afterYears' => 5, 'entitlement' => 30.0]]]);
    $result = AccrualCalculator::accrued($rule, '2021-07-01', '2026-01-01', '2026-12-31');

    // Six months at 21/yr, six at 30/yr.
    expect($result->accrued)->toBe(25.5)->and($result->annualEntitlement)->toBe(30.0);
});

it('credits the last partial month on the last day of service', function (): void {
    expect(AccrualCalculator::accrued(annual(), '2023-01-01', '2026-01-01', '2026-12-31', '2026-05-10')->accrued)->toBe(7.5)
        ->and(AccrualCalculator::accrued(annual(), '2023-01-01', '2026-01-01', '2026-05-09', '2026-05-10')->accrued)->toBe(7.0);
});

it('credits upfront and per-event types at the start of service', function (): void {
    $upfront = annual(['accrual' => 'yearly_upfront']);

    expect(AccrualCalculator::accrued($upfront, '2026-07-01', '2026-01-01', '2026-07-01')->accrued)->toBe(10.5)
        ->and(AccrualCalculator::accrued($upfront, '2026-07-01', '2026-01-01', '2026-06-30')->accrued)->toBe(0.0)
        ->and(AccrualCalculator::accrued(annual(['accrual' => 'yearly_upfront', 'proRata' => 'monthly']), '2026-07-20', '2026-01-01', '2026-12-31')->accrued)->toBe(8.5)
        ->and(AccrualCalculator::accrued(annual(['accrual' => 'none', 'entitlement' => 70.0, 'proRata' => 'none']), '2026-09-01', '2026-01-01', '2026-09-01')->accrued)->toBe(70.0);
});

it('carries over up to the limit with an expiry date', function (): void {
    expect(AccrualCalculator::carryOver(annual(), 8.0, '2027-01-01'))->toBe(['amount' => 5.0, 'expiresOn' => '2027-03-31'])
        ->and(AccrualCalculator::carryOver(annual(['carryOverMax' => null, 'carryOverExpiresMonths' => null]), 8.0, '2027-01-01'))->toBe(['amount' => 8.0, 'expiresOn' => null])
        ->and(AccrualCalculator::carryOver(annual(), -2.0, '2027-01-01'))->toBe(['amount' => 0.0, 'expiresOn' => null]);
});

it('is pure: the same input gives the same output', function (): void {
    $run = fn () => AccrualCalculator::accrued(annual(['tenureSteps' => [['afterYears' => 5, 'entitlement' => 30.0]]]), '2021-07-16', '2026-01-01', '2026-11-30', '2026-12-15');

    expect($run())->toEqual($run());
});
