<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Hiring\Listeners\SettleRequisitions;
use App\Domain\Hiring\Policies\HiringPolicy;
use App\Domain\Hiring\Workflows\OpenApprovedRequisition;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Events\InstanceFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Hiring wiring (Phase 6): the requisition approval's Update Record step, settling rejected or
 * withdrawn requisitions, and record-level gates.
 */
final class HiringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(RecordUpdates::class, function (RecordUpdates $updates): void {
            $updates->register($this->app->make(OpenApprovedRequisition::class));
        });
    }

    public function boot(): void
    {
        foreach (['viewRequisition', 'updateRequisition', 'updatePosting', 'viewApplication', 'updateApplication', 'viewCandidate', 'updateCandidate'] as $ability) {
            Gate::define("hiring.{$ability}", [HiringPolicy::class, $ability]);
        }

        Event::listen(InstanceFinished::class, SettleRequisitions::class);
    }
}
