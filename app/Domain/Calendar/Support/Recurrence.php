<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use Carbon\CarbonImmutable;

/**
 * Expands a simple repeat rule (D78) into occurrence starts. Pure. The rule repeats the local
 * wall-clock time in the event's timezone, so a 09:00 meeting stays at 09:00 across DST.
 *
 * Rule: `freq` daily|weekly|monthly, `interval` (default 1), `byweekday` ISO days for weekly
 * (default the first day's), and `until` (local date, inclusive) or `count` (occurrences,
 * skipped ones included, as in RFC 5545).
 */
final class Recurrence
{
    private const int MAX_STEPS = 5000;

    /**
     * @param  array{freq: string, interval?: int, byweekday?: list<int>, until?: string|null, count?: int|null}  $rule
     * @param  list<string>  $exdates  skipped starts (ISO 8601, any offset)
     * @return list<CarbonImmutable> starts in UTC that overlap [from, to)
     */
    public static function occurrences(array $rule, CarbonImmutable $firstStart, string $timezone, int $durationSeconds, CarbonImmutable $from, CarbonImmutable $to, array $exdates = []): array
    {
        $local = $firstStart->setTimezone($timezone);
        $time = $local->format('H:i:s');
        $interval = max(1, (int) ($rule['interval'] ?? 1));
        $until = $rule['until'] ?? null;
        $count = $rule['count'] ?? null;
        $skip = array_fill_keys(array_map(fn (string $d): int => CarbonImmutable::parse($d)->getTimestamp(), $exdates), true);
        $out = [];
        $n = 0;

        foreach (self::dates($rule['freq'], $interval, $rule['byweekday'] ?? [$local->dayOfWeekIso], $local) as $date) {
            if ($until !== null && $date > $until) {
                break;
            }

            $start = CarbonImmutable::parse($date.' '.$time, $timezone)->utc();
            $n++;

            if ($count !== null && $n > $count) {
                break;
            }

            if ($start->greaterThanOrEqualTo($to)) {
                break;
            }

            if ($start->getTimestamp() + $durationSeconds <= $from->getTimestamp() || isset($skip[$start->getTimestamp()])) {
                continue;
            }

            $out[] = $start;
        }

        return $out;
    }

    /**
     * Local candidate dates in order, from the first occurrence on.
     *
     * @param  list<int>  $weekdays
     * @return iterable<string>
     */
    private static function dates(string $freq, int $interval, array $weekdays, CarbonImmutable $first): iterable
    {
        $firstDate = $first->toDateString();
        $steps = 0;

        if ($freq === 'weekly') {
            $days = array_values(array_unique(array_filter($weekdays, fn (int $d): bool => $d >= 1 && $d <= 7)));
            sort($days);

            for ($week = $first->startOfWeek(CarbonImmutable::MONDAY); $steps < self::MAX_STEPS; $week = $week->addWeeks($interval), $steps++) {
                foreach ($days as $day) {
                    $date = $week->addDays($day - 1)->toDateString();

                    if ($date >= $firstDate) {
                        yield $date;
                    }
                }
            }

            return;
        }

        if ($freq === 'monthly') {
            $dayOfMonth = $first->day;

            for ($month = $first->startOfMonth(); $steps < self::MAX_STEPS; $month = $month->addMonthsNoOverflow($interval), $steps++) {
                if ($dayOfMonth <= $month->daysInMonth) {
                    yield $month->setDay($dayOfMonth)->toDateString();
                }
            }

            return;
        }

        for ($day = $first->startOfDay(); $steps < self::MAX_STEPS; $day = $day->addDays($interval), $steps++) {
            yield $day->toDateString();
        }
    }
}
