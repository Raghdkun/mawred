<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support\Calculators;

use Carbon\CarbonImmutable;

/**
 * Working days in an inclusive date range: neither a weekend day nor an observed holiday. Pure.
 */
final class WorkingDays
{
    /**
     * @param  list<int>  $weekend  ISO weekdays
     * @param  list<string>  $holidays  observed dates (Y-m-d)
     * @return list<string>
     */
    public static function between(string $from, string $to, array $weekend, array $holidays = []): array
    {
        $off = array_fill_keys($holidays, true);
        $day = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $dates = [];

        while ($day->lessThanOrEqualTo($end)) {
            if (! in_array($day->dayOfWeekIso, $weekend, true) && ! isset($off[$day->toDateString()])) {
                $dates[] = $day->toDateString();
            }

            $day = $day->addDay();
        }

        return $dates;
    }

    /**
     * @param  list<int>  $weekend
     * @param  list<string>  $holidays
     */
    public static function count(string $from, string $to, array $weekend, array $holidays = []): int
    {
        return count(self::between($from, $to, $weekend, $holidays));
    }
}
