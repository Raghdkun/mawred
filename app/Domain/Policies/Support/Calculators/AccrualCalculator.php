<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support\Calculators;

use App\Domain\Policies\Data\Values\LeaveRuleValue;
use Carbon\CarbonImmutable;

/**
 * Leave earned in one leave year. Pure: no database, no clock.
 *
 * - Accrual starts after the waiting period (hire date + eligibleAfterDays).
 * - `monthly` credits 1/12 of the yearly entitlement at each month end (or on the last day of
 *   service). Partial months follow proRata: `none` = whole month, `daily` = by days served,
 *   `monthly` = whole month if at least half of it was served, otherwise nothing.
 * - `yearly_upfront` credits the year at the start of service, pro-rated for a late start.
 * - `none` makes the full entitlement available (per-event types such as maternity).
 * - Tenure steps apply from the month (or year) in which the years of service are completed.
 * - Rounding is cumulative, always down (`half` = to 0.5, `whole` = to 1), so nothing is lost or
 *   granted twice as months add up.
 */
final class AccrualCalculator
{
    public static function accrued(LeaveRuleValue $rule, string $hireDate, string $yearStart, string $asOf, ?string $lastDay = null): AccrualResult
    {
        $hire = CarbonImmutable::parse($hireDate);
        $yearFrom = CarbonImmutable::parse($yearStart);
        $yearTo = $yearFrom->addYear()->subDay();
        $accruesFrom = $hire->addDays($rule->eligibleAfterDays);
        $until = CarbonImmutable::parse($asOf);
        $serviceEnd = $lastDay === null ? $yearTo : CarbonImmutable::parse($lastDay)->min($yearTo);
        $serviceStart = $accruesFrom->max($yearFrom);
        $annual = $rule->entitlementAfter(self::years($hire, $until));
        $result = fn (float $amount): AccrualResult => new AccrualResult(self::round($amount, $rule->rounding), $annual, $accruesFrom->toDateString());

        if ($serviceStart->greaterThan($serviceEnd) || $until->lessThan($serviceStart)) {
            return $result(0.0);
        }

        if ($rule->accrual === 'none') {
            return $result($rule->entitlementAfter(self::years($hire, $serviceStart)));
        }

        if ($rule->accrual === 'yearly_upfront') {
            return $result($rule->entitlementAfter(self::years($hire, $serviceStart)) * self::fraction($rule->proRata, $serviceStart, $yearTo, $yearFrom, $yearTo));
        }

        $amount = 0.0;

        for ($month = $yearFrom; $month->lessThanOrEqualTo($serviceEnd); $month = $month->addMonth()) {
            $monthEnd = $month->addMonth()->subDay();
            $servedFrom = $serviceStart->max($month);
            $servedTo = $serviceEnd->min($monthEnd);

            if ($servedFrom->greaterThan($servedTo)) {
                continue;
            }

            if ($until->lessThan($servedTo)) {
                break;
            }

            $amount += $rule->entitlementAfter(self::years($hire, $month)) / 12 * self::fraction($rule->proRata, $servedFrom, $servedTo, $month, $monthEnd);
        }

        return $result($amount);
    }

    /**
     * What moves to the next leave year, and when it expires (null = unlimited / never).
     *
     * @return array{amount: float, expiresOn: string|null}
     */
    public static function carryOver(LeaveRuleValue $rule, float $remaining, string $nextYearStart): array
    {
        $amount = max(0.0, $rule->carryOverMax === null ? $remaining : min($remaining, $rule->carryOverMax));
        $expires = $rule->carryOverExpiresMonths === null || $amount === 0.0
            ? null
            : CarbonImmutable::parse($nextYearStart)->addMonths($rule->carryOverExpiresMonths)->subDay()->toDateString();

        return ['amount' => $amount, 'expiresOn' => $expires];
    }

    /** Share of a period earned for serving [from, to] of [periodStart, periodEnd]. */
    private static function fraction(string $proRata, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): float
    {
        $served = $from->diffInDays($to, true) + 1;
        $length = $periodStart->diffInDays($periodEnd, true) + 1;

        return match ($proRata) {
            'daily' => $served / $length,
            'monthly' => $periodEnd->diffInMonths($periodStart, true) > 1
                ? self::wholeMonths($from, $periodEnd) / 12
                : ($served * 2 >= $length ? 1.0 : 0.0),
            default => 1.0,
        };
    }

    /** Months left in the year from a start date, counting the first one if it began by the 15th. */
    private static function wholeMonths(CarbonImmutable $from, CarbonImmutable $yearEnd): int
    {
        $months = (int) $from->startOfMonth()->diffInMonths($yearEnd->addDay(), true);

        return $from->day <= 15 ? $months : $months - 1;
    }

    private static function years(CarbonImmutable $hire, CarbonImmutable $at): int
    {
        return $at->lessThan($hire) ? 0 : (int) $hire->diffInYears($at, true);
    }

    private static function round(float $amount, string $rounding): float
    {
        // The epsilon keeps 20.999999… (twelve float twelfths) from rounding down a whole step.
        return match ($rounding) {
            'half' => floor($amount * 2 + 1e-9) / 2,
            'whole' => floor($amount + 1e-9),
            default => round($amount, 2),
        };
    }
}
