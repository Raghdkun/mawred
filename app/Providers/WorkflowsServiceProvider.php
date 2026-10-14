<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Workflows\Engine\NodeRegistry;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\WorkflowEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Workflow engine wiring (ADR-0009). Domains register their Update Record actions on
 * `RecordUpdates` from their own providers.
 */
final class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(RecordUpdates::class);
        $this->app->scoped(WorkflowEngine::class);
    }
}
