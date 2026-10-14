<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\People\Events\EmployeeHired;
use App\Domain\People\Events\EmployeeTerminated;
use App\Domain\Workflows\Engine\NodeRegistry;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Listeners\StartEmployeeChecklist;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Workflow engine wiring (ADR-0009). Domains register their Update Record actions on
 * `RecordUpdates` from their own providers; hiring and leaving start the checklists.
 */
final class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(RecordUpdates::class);
        $this->app->scoped(WorkflowEngine::class);
    }

    public function boot(): void
    {
        Event::listen([EmployeeHired::class, EmployeeTerminated::class], StartEmployeeChecklist::class);
    }
}
