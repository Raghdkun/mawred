<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\Policies\Data\Values\RestValue;
use Carbon\CarbonImmutable;

/**
 * Roster conflicts for one person (handoff 22). Pure: works on plain shift spans so the planner
 * and the server agree. Checks overlapping shifts, time off, rest between shifts, the weekly
 * hours cap (per ISO week) and consecutive working days.
 */
final class RosterConflicts
{
    /**
     * @param  list<array{id: string, date: string, start: int, end: int, paid: int}>  $shifts  start/end in minutes from the date's midnight
     * @param  list<string>  $offDates  dates with leave or a day off (pending or approved)
     * @return array<string, list<string>> shift id => conflict codes (overlap, time_off, rest, weekly_hours, consecutive_days)
     */
    public static function find(array $shifts, array $offDates, RestValue $rest): array
    {
        $conflicts = [];
        $absolute = array_map(function (array $s): array {
            $day = intdiv(CarbonImmutable::parse($s['date'])->getTimestamp(), 86400) * 1440;

            return [...$s, 'from' => $day + $s['start'], 'to' => $day + $s['end']];
        }, $shifts);
        usort($absolute, fn (array $a, array $b): int => $a['from'] <=> $b['from']);
        $add = function (string $id, string $code) use (&$conflicts): void {
            $conflicts[$id] = array_values(array_unique([...$conflicts[$id] ?? [], $code]));
        };
        $off = array_fill_keys($offDates, true);

        foreach ($absolute as $i => $shift) {
            if (isset($off[$shift['date']])) {
                $add($shift['id'], 'time_off');
            }

            $next = $absolute[$i + 1] ?? null;

            if ($next === null) {
                continue;
            }

            if ($next['from'] < $shift['to']) {
                $add($shift['id'], 'overlap');
                $add($next['id'], 'overlap');
            } elseif ($rest->minRestHours * 60 > $next['from'] - $shift['to'] && $next['date'] !== $shift['date']) {
                $add($next['id'], 'rest');
            }
        }

        $weeks = [];

        foreach ($absolute as $shift) {
            $week = CarbonImmutable::parse($shift['date'])->format('o-W');
            $weeks[$week][] = $shift;
        }

        foreach ($weeks as $weekShifts) {
            $minutes = 0;

            foreach ($weekShifts as $shift) {
                $minutes += $shift['paid'];

                if ($minutes > $rest->maxWeeklyHours * 60) {
                    $add($shift['id'], 'weekly_hours');
                }
            }
        }

        $dates = array_values(array_unique(array_column($absolute, 'date')));
        sort($dates);
        $run = 0;
        $previous = null;

        foreach ($dates as $date) {
            $run = $previous !== null && CarbonImmutable::parse($previous)->addDay()->toDateString() === $date ? $run + 1 : 1;
            $previous = $date;

            if ($run > $rest->maxConsecutiveDays) {
                foreach ($absolute as $shift) {
                    if ($shift['date'] === $date) {
                        $add($shift['id'], 'consecutive_days');
                    }
                }
            }
        }

        return $conflicts;
    }
}
