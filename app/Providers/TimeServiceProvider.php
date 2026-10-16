<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Attendance\Listeners\SettleOvertimeRequests;
use App\Domain\Attendance\Policies\AttendancePolicy;
use App\Domain\Attendance\Workflows\ApplyAttendanceCorrection;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Policies\CalendarEventPolicy;
use App\Domain\Scheduling\Workflows\ApplyShiftSwap;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Events\InstanceFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Scheduling, attendance and calendar wiring (Phase 5b): the swap and correction Update Record
 * actions, overtime settlement when Overtime requests are decided, and record-level access.
 */
final class TimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(RecordUpdates::class, function (RecordUpdates $updates): void {
            $updates->register($this->app->make(ApplyShiftSwap::class));
            $updates->register($this->app->make(ApplyAttendanceCorrection::class));
        });
    }

    public function boot(): void
    {
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::define('viewAttendance', [AttendancePolicy::class, 'view']);
        Gate::define('correctAttendance', [AttendancePolicy::class, 'correct']);
        Event::listen(InstanceFinished::class, SettleOvertimeRequests::class);
    }
}
