<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Requests\Listeners\SyncRequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Policies\RequestPolicy;
use App\Domain\Workflows\Events\InstanceFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Requests wiring: record-level policy and keeping request status in step with workflows.
 */
final class RequestsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(EmployeeRequest::class, RequestPolicy::class);
        Event::listen(InstanceFinished::class, SyncRequestStatus::class);
    }
}
