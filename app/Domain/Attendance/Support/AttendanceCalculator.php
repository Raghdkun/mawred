<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Policies\Data\Values\GraceValue;
use App\Domain\Policies\Data\Values\OvertimeValue;
use App\Domain\Policies\Support\Calculators\LatenessCalculator;
use App\Domain\Policies\Support\Calculators\OvertimeCalculator;
use App\Domain\Policies\Support\Calculators\OvertimeResult;
use App\Domain\Scheduling\Support\ExpectedDay;

/**
 * Turns a day's punches and its expected schedule into an attendance day. Pure: no database, no
 * clock. Punches pair in → out in time order; a second check-in while in, or a check-out while
 * out, is ignored and flagged. Lateness follows the grace rules (D50) against the schedule after
 * any approved permission; overtime follows the overtime rules.
 */
final class AttendanceCalculator
{
    /**
     * @param  list<array{kind: 'in'|'out', at: int}>  $punches  minutes from the day's local midnight
     * @param  bool  $closed  the day is over: no check-in means absent, an open check-in means a missing check-out
     */
    public static function day(ExpectedDay $expected, array $punches, GraceValue $grace, OvertimeValue $overtime, bool $closed): DayResult
    {
        usort($punches, fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        $intervals = [];
        $flags = [];
        $open = null;

        foreach ($punches as $punch) {
            if ($punch['kind'] === 'in') {
                if ($open !== null) {
                    $flags[] = 'double_in';

                    continue;
                }

                $open = $punch['at'];
            } elseif ($open === null) {
                $flags[] = 'out_without_in';
            } else {
                $intervals[] = [$open, $punch['at']];
                $open = null;
            }
        }

        if ($open !== null && $closed) {
            $flags[] = 'missing_out';
        }

        $ins = array_column(array_filter($punches, fn (array $p): bool => $p['kind'] === 'in'), 'at');
        $firstIn = $ins === [] ? null : min($ins);
        $lastOut = $intervals === [] ? null : max(array_column($intervals, 1));
        $worked = array_sum(array_map(fn (array $i): int => max(0, $i[1] - $i[0]), $intervals));
        $flags = array_values(array_unique($flags));

        if ($expected->reason === 'leave' || $expected->reason === 'day_off') {
            return new DayResult(DayStatus::OnLeave, $firstIn, $lastOut, $worked, 0, 0, new OvertimeResult, $flags);
        }

        $ot = OvertimeCalculator::forDay($overtime, $intervals, $expected->expectsWork() ? $expected->paidMinutes : 0, $expected->kind);

        if (! $expected->expectsWork()) {
            return new DayResult($worked > 0 || $firstIn !== null ? DayStatus::Present : DayStatus::Off, $firstIn, $lastOut, $worked, 0, 0, $ot, $flags);
        }

        if ($firstIn === null) {
            return new DayResult($closed ? DayStatus::Absent : DayStatus::Scheduled, null, null, 0, 0, 0, $ot, $flags);
        }

        $start = $expected->effectiveStart() ?? 0;
        $end = $expected->effectiveEnd() ?? 0;
        $lateness = LatenessCalculator::forDay($grace, $start, max($start, $end), $firstIn, $lastOut);

        $status = match (true) {
            in_array('missing_out', $flags, true) => DayStatus::Incomplete,
            $lateness['late'] > 0 => DayStatus::Late,
            default => DayStatus::Present,
        };

        return new DayResult($status, $firstIn, $lastOut, $worked, $lateness['late'], $closed || $lastOut !== null && $open === null ? $lateness['earlyLeave'] : 0, $ot, $flags);
    }
}
