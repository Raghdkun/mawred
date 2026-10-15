<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Leave\Listeners\SettleLeave;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Policies\LeavePolicy;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Workflows\Events\InstanceFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Leave wiring (Phase 5a): balance access, settling holds when workflows decide, and one
 * employee-calendar memo per request or job.
 */
final class LeaveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EmployeeDays::class);
    }

    public function boot(): void
    {
        Gate::define('viewLeave', [LeavePolicy::class, 'view']);
        Gate::define('adjustLeave', [LeavePolicy::class, 'adjust']);
        Gate::policy(LeaveRequest::class, LeavePolicy::class);
        Event::listen(InstanceFinished::class, SettleLeave::class);
    }
}
