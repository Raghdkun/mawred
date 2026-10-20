<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Reports\Models\ReportSchedule;
use App\Domain\Reports\Support\ScheduleClock;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;

/** Pauses a schedule, or resumes it from the next matching hour (no catching up on missed runs). */
final readonly class PauseReportSchedule
{
    public function handle(Actor $actor, ReportSchedule $schedule, bool $paused): ReportSchedule
    {
        $schedule->forceFill($paused
            ? ['paused_at' => CarbonImmutable::now(), 'paused_reason' => null]
            : ['paused_at' => null, 'paused_reason' => null, 'next_run_at' => ScheduleClock::next($schedule->frequency, $schedule->weekday, $schedule->month_day, $schedule->hour, $schedule->timezone, CarbonImmutable::now())],
        )->save();

        return $schedule;
    }
}
