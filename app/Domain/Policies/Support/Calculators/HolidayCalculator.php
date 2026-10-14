<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support\Calculators;

use App\Domain\Policies\Enums\HolidayType;
use App\Domain\Policies\Enums\ObservedRule;
use App\Domain\Policies\Support\HolidayOccurrence;
use App\Domain\Policies\Support\HolidayRuleSpec;
use Carbon\CarbonImmutable;

/**
 * Expands holiday rules into dated occurrences. Pure: the same rules, range and weekend always
 * give the same days, and nothing is read from the database or the clock.
 */
final class HolidayCalculator
{
    /**
     * Occurrences observed within [from, to], sorted by observed date.
     *
     * @param  iterable<HolidayRuleSpec>  $rules
     * @param  list<int>  $weekend  ISO weekdays (1 = Monday)
     * @return list<HolidayOccurrence>
     */
    public static function between(iterable $rules, string $from, string $to, array $weekend): array
    {
        $firstYear = (int) substr($from, 0, 4);
        $lastYear = (int) substr($to, 0, 4);
        $occurrences = [];

        foreach ($rules as $rule) {
            // A shift can cross New Year (Saturday 1 January is observed on Friday 31 December).
            for ($year = $firstYear - 1; $year <= $lastYear + 1; $year++) {
                foreach (self::forYear($rule, $year, $weekend) as $occurrence) {
                    if ($occurrence->observedOn >= $from && $occurrence->observedOn <= $to) {
                        $occurrences[] = $occurrence;
                    }
                }
            }
        }

        usort($occurrences, fn (HolidayOccurrence $a, HolidayOccurrence $b): int => [$a->observedOn, $a->ruleId, $a->day] <=> [$b->observedOn, $b->ruleId, $b->day]);

        return $occurrences;
    }

    /**
     * The occurrences of one rule whose first day falls in the given year.
     *
     * @param  list<int>  $weekend
     * @return list<HolidayOccurrence>
     */
    public static function forYear(HolidayRuleSpec $rule, int $year, array $weekend): array
    {
        if (($rule->fromYear !== null && $year < $rule->fromYear) || ($rule->toYear !== null && $year > $rule->toYear)) {
            return [];
        }

        $start = self::firstDay($rule, $year);

        if (! $start instanceof CarbonImmutable) {
            return [];
        }

        $length = max(1, $rule->durationDays);
        $days = [];

        for ($i = 0; $i < $length; $i++) {
            $days[] = $start->addDays($i);
        }

        // Shifted days never land on another day of the same holiday.
        $taken = array_fill_keys(array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $days), true);
        $occurrences = [];

        foreach ($days as $index => $day) {
            $observed = self::isWeekend($day, $weekend) ? self::shift($day, $rule->observed, $weekend, $taken) : $day;
            $taken[$observed->toDateString()] = true;

            $occurrences[] = new HolidayOccurrence(
                calendarId: $rule->calendarId,
                ruleId: $rule->id,
                name: $rule->name,
                date: $day->toDateString(),
                observedOn: $observed->toDateString(),
                hijriBased: $rule->hijriBased,
                day: $index + 1,
                days: $length,
            );
        }

        return $occurrences;
    }

    private static function firstDay(HolidayRuleSpec $rule, int $year): ?CarbonImmutable
    {
        return match ($rule->type) {
            HolidayType::Fixed => $rule->month !== null && $rule->day !== null && checkdate($rule->month, $rule->day, $year)
                ? self::firstOfMonth($year, $rule->month)->setDay($rule->day)
                : null,
            HolidayType::NthWeekday => self::nthWeekday($year, $rule->month, $rule->weekday, $rule->nth),
            HolidayType::OneOff => $rule->date !== null && (int) substr($rule->date, 0, 4) === $year ? CarbonImmutable::parse($rule->date) : null,
        };
    }

    /**
     * The nth given weekday of a month (nth = -1 for the last one), or null if there is none.
     */
    private static function nthWeekday(int $year, ?int $month, ?int $weekday, ?int $nth): ?CarbonImmutable
    {
        if ($month === null || $weekday === null || $nth === null || $nth === 0) {
            return null;
        }

        if ($nth < 0) {
            $last = self::firstOfMonth($year, $month)->endOfMonth()->startOfDay();

            return $last->subDays(($last->dayOfWeekIso - $weekday + 7) % 7);
        }

        $first = self::firstOfMonth($year, $month);
        $date = $first->addDays(($weekday - $first->dayOfWeekIso + 7) % 7 + ($nth - 1) * 7);

        return $date->month === $month ? $date : null;
    }

    private static function firstOfMonth(int $year, int $month): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month));
    }

    /**
     * @param  list<int>  $weekend
     * @param  array<string, true>  $taken
     */
    private static function shift(CarbonImmutable $day, ObservedRule $rule, array $weekend, array $taken): CarbonImmutable
    {
        if ($rule === ObservedRule::None || count(array_unique($weekend)) >= 7) {
            return $day;
        }

        $free = fn (CarbonImmutable $candidate): bool => ! self::isWeekend($candidate, $weekend) && ! isset($taken[$candidate->toDateString()]);
        $before = $day->subDay();
        $after = $day->addDay();

        while (! $free($before)) {
            $before = $before->subDay();
        }

        while (! $free($after)) {
            $after = $after->addDay();
        }

        return match ($rule) {
            ObservedRule::PreviousWorkingDay => $before,
            ObservedRule::NextWorkingDay => $after,
            // Ties go forward, so a holiday between two weekend days moves to the next working day.
            ObservedRule::NearestWeekday => $day->diffInDays($before, true) < $day->diffInDays($after, true) ? $before : $after,
        };
    }

    /**
     * @param  list<int>  $weekend
     */
    private static function isWeekend(CarbonImmutable $day, array $weekend): bool
    {
        return in_array($day->dayOfWeekIso, $weekend, true);
    }
}
