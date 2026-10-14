<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support\Calculators;

use App\Domain\Policies\Data\Values\OvertimeValue;
use App\Domain\Policies\Enums\DayKind;

/**
 * Overtime for one day. Pure: no database, no clock.
 *
 * - On a workday, overtime is the time worked past the scheduled minutes (the latest minutes of
 *   the day). On a weekend or holiday, all time worked is overtime.
 * - Below the daily threshold nothing counts; once reached, all of it counts.
 * - The total rounds down to the rounding block, dropping the latest minutes.
 * - Minutes inside the night window use the night multiplier when it is higher than the day's.
 */
final class OvertimeCalculator
{
    /**
     * @param  list<array{0: int, 1: int}>  $worked  [start, end) in minutes from the start of the day;
     *                                               an end past 1440 continues after midnight
     */
    public static function forDay(OvertimeValue $rule, array $worked, int $scheduledMinutes, DayKind $kind): OvertimeResult
    {
        if (! $rule->enabled) {
            return new OvertimeResult;
        }

        $intervals = self::merge($worked);
        $total = array_sum(array_map(fn (array $i): int => $i[1] - $i[0], $intervals));
        $overtime = $kind === DayKind::Workday ? $total - $scheduledMinutes : $total;

        if ($overtime <= 0 || $overtime < $rule->dailyThresholdMinutes) {
            return new OvertimeResult;
        }

        $counted = intdiv($overtime, max(1, $rule->roundingMinutes)) * max(1, $rule->roundingMinutes);
        // Overtime is the tail of the day; rounding then drops its latest minutes.
        $segments = self::slice($intervals, $total - $overtime, $total - $overtime + $counted);

        $dayMultiplier = match ($kind) {
            DayKind::Workday => $rule->weekday,
            DayKind::Weekend => $rule->weekend,
            DayKind::Holiday => $rule->holiday,
        };
        $nightMinutes = $rule->night > $dayMultiplier ? self::overlap($segments, self::nightWindows($rule)) : 0;
        $buckets = [];

        if ($counted - $nightMinutes > 0) {
            $buckets[$kind->value] = ['multiplier' => $dayMultiplier, 'minutes' => $counted - $nightMinutes];
        }

        if ($nightMinutes > 0) {
            $buckets['night'] = ['multiplier' => $rule->night, 'minutes' => $nightMinutes];
        }

        return new OvertimeResult($buckets);
    }

    /**
     * Trims a day's result so the month stays within the cap (null = no cap).
     */
    public static function capToMonth(OvertimeValue $rule, OvertimeResult $day, int $alreadyThisMonth): OvertimeResult
    {
        if ($rule->monthlyCapMinutes === null) {
            return $day;
        }

        $left = max(0, $rule->monthlyCapMinutes - $alreadyThisMonth);
        $buckets = [];

        foreach ($day->buckets as $name => $bucket) {
            $minutes = min($bucket['minutes'], $left);
            $left -= $minutes;

            if ($minutes > 0) {
                $buckets[$name] = ['multiplier' => $bucket['multiplier'], 'minutes' => $minutes];
            }
        }

        return new OvertimeResult($buckets);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    private static function merge(array $intervals): array
    {
        $valid = array_values(array_filter($intervals, fn (array $i): bool => $i[1] > $i[0]));
        usort($valid, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];

        foreach ($valid as [$start, $end]) {
            $last = array_key_last($merged);

            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * The worked time between two offsets into the day's worked minutes.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    private static function slice(array $intervals, int $from, int $to): array
    {
        $segments = [];
        $offset = 0;

        foreach ($intervals as [$start, $end]) {
            $length = $end - $start;
            $a = max($from, $offset);
            $b = min($to, $offset + $length);

            if ($a < $b) {
                $segments[] = [$start + $a - $offset, $start + $b - $offset];
            }

            $offset += $length;
        }

        return $segments;
    }

    /**
     * Night windows around the day (yesterday's, today's, tomorrow's), in day minutes.
     *
     * @return list<array{0: int, 1: int}>
     */
    private static function nightWindows(OvertimeValue $rule): array
    {
        $start = self::minutes($rule->nightStartsAt);
        $end = self::minutes($rule->nightEndsAt);
        $windows = [];

        if ($start === $end) {
            return [];
        }

        foreach ([-1, 0, 1] as $day) {
            $windows[] = $start < $end
                ? [$day * 1440 + $start, $day * 1440 + $end]
                : [$day * 1440 + $start, ($day + 1) * 1440 + $end];
        }

        return $windows;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $segments
     * @param  list<array{0: int, 1: int}>  $windows
     */
    private static function overlap(array $segments, array $windows): int
    {
        $minutes = 0;

        foreach ($segments as [$start, $end]) {
            foreach ($windows as [$from, $to]) {
                $minutes += max(0, min($end, $to) - max($start, $from));
            }
        }

        return $minutes;
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time) + [1 => '0']);

        return $hours * 60 + $minutes;
    }
}
