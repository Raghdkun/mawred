<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Queue dashboard: holders of `system.view` (ADR-0005), anyone locally.
        Gate::define('viewHorizon', fn (?User $user = null): bool => app()->isLocal() || ($user instanceof User && $user->can('system.view')));
    }
}
