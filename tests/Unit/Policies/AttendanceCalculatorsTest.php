<?php

declare(strict_types=1);

use App\Domain\Policies\Data\Values\GraceValue;
use App\Domain\Policies\Data\Values\OvertimeValue;
use App\Domain\Policies\Enums\DayKind;
use App\Domain\Policies\Support\Calculators\LatenessCalculator;
use App\Domain\Policies\Support\Calculators\OvertimeCalculator;

function hm(string $time, int $day = 0): int
{
    [$h, $m] = explode(':', $time);

    return $day * 1440 + (int) $h * 60 + (int) $m;
}

it('counts the time past the schedule, rounded down to the block', function (): void {
    $result = OvertimeCalculator::forDay(new OvertimeValue, [[hm('08:00'), hm('19:10')]], 480, DayKind::Workday);

    expect($result->buckets)->toBe(['workday' => ['multiplier' => 1.5, 'minutes' => 180]])
        ->and($result->weightedMinutes())->toBe(270.0);
});

it('uses the night multiplier for late overtime, but not when the day pays more', function (): void {
    expect(OvertimeCalculator::forDay(new OvertimeValue, [[hm('14:00'), hm('23:30')]], 480, DayKind::Workday)->buckets)
        ->toBe(['night' => ['multiplier' => 1.75, 'minutes' => 90]])
        ->and(OvertimeCalculator::forDay(new OvertimeValue, [[hm('20:00'), hm('23:00')]], 0, DayKind::Holiday)->buckets)
        ->toBe(['holiday' => ['multiplier' => 2.0, 'minutes' => 180]]);
});

it('treats all weekend work as overtime', function (): void {
    expect(OvertimeCalculator::forDay(new OvertimeValue, [[hm('10:00'), hm('14:00')]], 480, DayKind::Weekend)->minutes())->toBe(240);
});

it('handles shifts past midnight and breaks', function (): void {
    expect(OvertimeCalculator::forDay(new OvertimeValue, [[hm('22:00'), hm('07:00', 1)]], 480, DayKind::Workday)->buckets)
        ->toBe(['workday' => ['multiplier' => 1.5, 'minutes' => 60]])
        ->and(OvertimeCalculator::forDay(new OvertimeValue, [[hm('13:00'), hm('18:00')], [hm('08:00'), hm('12:00')], [hm('17:00'), hm('17:30')]], 480, DayKind::Workday)->minutes())->toBe(60);
});

it('ignores overtime below the threshold or when disabled', function (): void {
    $worked = [[hm('09:00'), hm('17:20')]];

    expect(OvertimeCalculator::forDay(new OvertimeValue(dailyThresholdMinutes: 30, roundingMinutes: 1), $worked, 480, DayKind::Workday)->minutes())->toBe(0)
        ->and(OvertimeCalculator::forDay(new OvertimeValue(roundingMinutes: 1), $worked, 480, DayKind::Workday)->minutes())->toBe(20)
        ->and(OvertimeCalculator::forDay(new OvertimeValue(enabled: false), $worked, 0, DayKind::Weekend)->minutes())->toBe(0);
});

it('caps the month', function (): void {
    $rule = new OvertimeValue(monthlyCapMinutes: 600);
    $day = OvertimeCalculator::forDay($rule, [[hm('08:00'), hm('19:00')]], 480, DayKind::Workday);

    expect(OvertimeCalculator::capToMonth($rule, $day, 560)->minutes())->toBe(40)
        ->and(OvertimeCalculator::capToMonth($rule, $day, 600)->buckets)->toBe([])
        ->and(OvertimeCalculator::capToMonth(new OvertimeValue, $day, 99999))->toBe($day);
});

it('applies the grace, counting from the scheduled time or the grace end', function (): void {
    $start = hm('09:00');
    $end = hm('17:00');

    expect(LatenessCalculator::forDay(new GraceValue, $start, $end, hm('09:10'), $end))->toBe(['late' => 0, 'earlyLeave' => 0])
        ->and(LatenessCalculator::forDay(new GraceValue, $start, $end, hm('09:20'), hm('16:50')))->toBe(['late' => 20, 'earlyLeave' => 10])
        ->and(LatenessCalculator::forDay(new GraceValue(countFrom: 'grace_end'), $start, $end, hm('09:20'), null))->toBe(['late' => 5, 'earlyLeave' => 0]);
});

it('is pure: the same input gives the same output', function (): void {
    $run = fn () => OvertimeCalculator::forDay(new OvertimeValue(monthlyCapMinutes: 100), [[hm('15:00'), hm('01:30', 1)]], 480, DayKind::Workday);

    expect($run())->toEqual($run());
});
