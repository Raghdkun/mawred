<?php

declare(strict_types=1);

use App\Domain\Reports\Enums\ScheduleFrequency;
use App\Domain\Reports\Support\ScheduleClock;
use Carbon\CarbonImmutable;

/*
| D120: a schedule's next run is the first matching day and hour after now, in the owner's
| timezone, returned in UTC.
*/
it('finds the next run for each frequency', function (ScheduleFrequency $frequency, ?int $weekday, ?int $monthDay, string $after, string $expected): void {
    $next = ScheduleClock::next($frequency, $weekday, $monthDay, 7, 'Asia/Riyadh', CarbonImmutable::parse($after, 'UTC'));

    expect($next->toIso8601String())->toBe(CarbonImmutable::parse($expected, 'UTC')->toIso8601String());
})->with([
    'daily, later today' => [ScheduleFrequency::Daily, null, null, '2026-10-20 02:00', '2026-10-20 04:00'],
    'daily, already past' => [ScheduleFrequency::Daily, null, null, '2026-10-20 04:00', '2026-10-21 04:00'],
    'weekly on Sunday' => [ScheduleFrequency::Weekly, 7, null, '2026-10-20 05:00', '2026-10-25 04:00'],
    'weekly, the same day before the hour' => [ScheduleFrequency::Weekly, 2, null, '2026-10-20 03:00', '2026-10-20 04:00'],
    'monthly on the 1st' => [ScheduleFrequency::Monthly, null, 1, '2026-10-20 05:00', '2026-11-01 04:00'],
    'monthly over a year end' => [ScheduleFrequency::Monthly, null, 28, '2026-12-29 05:00', '2027-01-28 04:00'],
]);
