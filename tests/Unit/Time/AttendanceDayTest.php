<?php

declare(strict_types=1);

use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Attendance\Enums\GeofenceResult;
use App\Domain\Attendance\Support\AttendanceCalculator;
use App\Domain\Attendance\Support\Geofence;
use App\Domain\Attendance\Support\KioskCodes;
use App\Domain\Policies\Data\Values\GraceValue;
use App\Domain\Policies\Data\Values\OvertimeValue;
use App\Domain\Policies\Data\Values\RestValue;
use App\Domain\Policies\Enums\DayKind;
use App\Domain\Scheduling\Support\ExpectedDay;
use App\Domain\Scheduling\Support\RosterConflicts;

/*
| Phase 5b pure pieces: the attendance day, roster conflicts, the branch area and kiosk codes.
*/
function nineToFive(DayKind $kind = DayKind::Workday, array $excused = []): ExpectedDay
{
    return new ExpectedDay('policy', null, 9 * 60, 17 * 60, 7 * 60, $kind, 'Asia/Riyadh', $excused);
}

function punches(string ...$times): array
{
    return array_map(function (string $t): array {
        [$kind, $time] = explode('@', $t);
        [$h, $m] = array_map(intval(...), explode(':', $time));

        return ['kind' => $kind, 'at' => $h * 60 + $m];
    }, $times);
}

it('marks a day present, late, absent or incomplete', function (): void {
    $grace = new GraceValue(lateMinutes: 10);
    $ot = new OvertimeValue(enabled: false);

    expect(AttendanceCalculator::day(nineToFive(), punches('in@09:05', 'out@17:00'), $grace, $ot, true)->status)->toBe(DayStatus::Present);

    $late = AttendanceCalculator::day(nineToFive(), punches('in@09:25', 'out@17:00'), $grace, $ot, true);
    expect($late->status)->toBe(DayStatus::Late)->and($late->late)->toBe(25)->and($late->worked)->toBe(455);

    expect(AttendanceCalculator::day(nineToFive(), [], $grace, $ot, true)->status)->toBe(DayStatus::Absent)
        ->and(AttendanceCalculator::day(nineToFive(), [], $grace, $ot, false)->status)->toBe(DayStatus::Scheduled);

    $open = AttendanceCalculator::day(nineToFive(), punches('in@09:00'), $grace, $ot, true);
    expect($open->status)->toBe(DayStatus::Incomplete)->and($open->flags)->toBe(['missing_out']);
});

it('excuses lateness covered by an approved permission and ignores stray punches', function (): void {
    $day = AttendanceCalculator::day(nineToFive(DayKind::Workday, [[9 * 60, 11 * 60]]), punches('in@10:55', 'in@11:00', 'out@17:00', 'out@17:05'), new GraceValue(lateMinutes: 0), new OvertimeValue(enabled: false), true);

    expect($day->status)->toBe(DayStatus::Present)
        ->and($day->late)->toBe(0)
        ->and($day->flags)->toBe(['double_in', 'out_without_in']);
});

it('counts overtime past the schedule and all work on a weekend', function (): void {
    $rule = new OvertimeValue(roundingMinutes: 15);
    $workday = AttendanceCalculator::day(nineToFive(), punches('in@09:00', 'out@19:10'), new GraceValue, $rule, true);
    $weekend = AttendanceCalculator::day(new ExpectedDay('none', 'weekend', null, null, 0, DayKind::Weekend, 'UTC'), punches('in@10:00', 'out@12:00'), new GraceValue, $rule, true);

    expect($workday->overtime->minutes())->toBe(180)
        ->and($weekend->status)->toBe(DayStatus::Present)
        ->and($weekend->overtime->minutes())->toBe(120);
});

it('treats leave as on leave whatever the punches say', function (): void {
    $leave = new ExpectedDay('none', 'leave', null, null, 0, DayKind::Workday, 'UTC');

    expect(AttendanceCalculator::day($leave, [], new GraceValue, new OvertimeValue, true)->status)->toBe(DayStatus::OnLeave);
});

it('finds overlaps, short rests, long weeks, long runs and shifts on time off', function (): void {
    $shift = fn (string $id, string $date, int $start, int $end): array => ['id' => $id, 'date' => $date, 'start' => $start, 'end' => $end, 'paid' => $end - $start];
    $rest = new RestValue(minRestHours: 11, maxConsecutiveDays: 3, maxWeeklyHours: 20);

    $conflicts = RosterConflicts::find([
        $shift('a', '2026-03-02', 9 * 60, 17 * 60),
        $shift('b', '2026-03-02', 16 * 60, 20 * 60),
        $shift('c', '2026-03-03', 22 * 60, 30 * 60),
        $shift('d', '2026-03-04', 9 * 60, 17 * 60),
        $shift('e', '2026-03-05', 9 * 60, 17 * 60),
    ], ['2026-03-05'], $rest);

    expect($conflicts['a'])->toBe(['overlap'])
        ->and($conflicts['b'])->toBe(['overlap'])
        ->and($conflicts['d'])->toContain('rest', 'weekly_hours')
        ->and($conflicts['e'])->toContain('time_off', 'consecutive_days');
});

it('checks the branch area with the phone accuracy in the person\'s favour', function (): void {
    // About 150 m north of the point.
    expect(Geofence::check(24.7136, 46.6753, 100, 24.71495, 46.6753, 10))->toBe(GeofenceResult::Outside)
        ->and(Geofence::check(24.7136, 46.6753, 100, 24.71495, 46.6753, 60))->toBe(GeofenceResult::Inside)
        ->and(Geofence::check(24.7136, 46.6753, 100, null, null, null))->toBe(GeofenceResult::Unknown)
        ->and(Geofence::check(null, null, null, 24.7, 46.6, 5))->toBe(GeofenceResult::Unknown);
});

it('accepts a kiosk code only while it is on screen', function (): void {
    $secretFor = fn (string $ulid): ?string => $ulid === '01KIOSK0000000000000000000' ? 'secret' : null;
    $code = KioskCodes::code('01KIOSK0000000000000000000', 'secret', 1_000_000);

    expect(KioskCodes::verify($code, $secretFor, 1_000_000))->toBe('01KIOSK0000000000000000000')
        ->and(KioskCodes::verify($code, $secretFor, 1_000_000 + KioskCodes::SLOT_SECONDS))->toBe('01KIOSK0000000000000000000')
        ->and(KioskCodes::verify($code, $secretFor, 1_000_000 + 3 * KioskCodes::SLOT_SECONDS))->toBeNull()
        ->and(KioskCodes::verify(str_replace('.', '.9', $code), $secretFor, 1_000_000))->toBeNull()
        ->and(KioskCodes::verify(KioskCodes::code('01KIOSK0000000000000000000', 'other', 1_000_000), $secretFor, 1_000_000))->toBeNull();
});
