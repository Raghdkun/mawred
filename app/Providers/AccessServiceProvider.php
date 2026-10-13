<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Models\User;
use App\Domain\Access\Policies\UserPolicy;
use App\Domain\Access\Support\AccessEpoch;
use App\Domain\Access\Support\PermissionCatalog;
use App\Domain\Access\Support\ScopeResolver;
use App\Listeners\Auth\RecordAuthenticationEvents;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * Access wiring (ADR-0005): every `module.action` ability goes through the ScopeResolver, and any
 * role/permission change invalidates cached access.
 */
final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // OrgScopePredicates is bound by PeopleServiceProvider (needs People + Organization).
        $this->app->scoped(ScopeResolver::class);
        $this->app->singleton(AccessEpoch::class);
    }

    public function boot(): void
    {
        Gate::before(function (mixed $user, string $ability): ?bool {
            if (! $user instanceof User || ! PermissionCatalog::exists($ability)) {
                return null;
            }

            return $this->app->make(ScopeResolver::class)->can($user, $ability) ?: null;
        });

        Gate::policy(User::class, UserPolicy::class);

        // Operational dashboards (Pulse, API docs) follow the `system.view` permission.
        Gate::define('viewPulse', fn (?User $user = null): bool => $user instanceof User && $user->can('system.view'));
        Gate::define('viewApiDocs', fn (?User $user = null): bool => $this->app->isLocal() || ($user instanceof User && $user->can('system.view')));
        Event::subscribe(RecordAuthenticationEvents::class);

        Event::listen(
            [RoleAttachedEvent::class, RoleDetachedEvent::class, PermissionAttachedEvent::class, PermissionDetachedEvent::class],
            fn () => $this->app->make(AccessEpoch::class)->bump(),
        );
    }
}
