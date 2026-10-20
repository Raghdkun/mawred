<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Reports\Enums\ScheduleFrequency;
use Carbon\CarbonImmutable;

/**
 * When a schedule runs next (D120): the first time after a moment that falls on its day and hour
 * in the owner's timezone, returned in UTC. Pure, so it runs in the Unit suite.
 */
final class ScheduleClock
{
    public static function next(ScheduleFrequency $frequency, ?int $weekday, ?int $monthDay, int $hour, string $timezone, CarbonImmutable $after): CarbonImmutable
    {
        $local = $after->setTimezone($timezone);
        $candidate = $local->setTime($hour, 0);

        $candidate = match ($frequency) {
            ScheduleFrequency::Daily => $candidate,
            ScheduleFrequency::Weekly => $candidate->addDays((($weekday ?? 1) - $candidate->dayOfWeekIso + 7) % 7),
            ScheduleFrequency::Monthly => $candidate->setDay(min($monthDay ?? 1, 28)),
        };

        while ($candidate->lessThanOrEqualTo($local)) {
            $candidate = match ($frequency) {
                ScheduleFrequency::Daily => $candidate->addDay(),
                ScheduleFrequency::Weekly => $candidate->addWeek(),
                ScheduleFrequency::Monthly => $candidate->addMonthNoOverflow()->setDay(min($monthDay ?? 1, 28)),
            };
        }

        return $candidate->utc();
    }
}
