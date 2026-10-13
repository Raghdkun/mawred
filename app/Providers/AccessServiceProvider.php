<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Contracts\OrgScopePredicates;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\AccessEpoch;
use App\Domain\Access\Support\NullOrgScopePredicates;
use App\Domain\Access\Support\PermissionCatalog;
use App\Domain\Access\Support\ScopeResolver;
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
        // Phase 1 rebinds this to the real org predicates (People + Organization).
        $this->app->bind(OrgScopePredicates::class, NullOrgScopePredicates::class);
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

        Event::listen(
            [RoleAttachedEvent::class, RoleDetachedEvent::class, PermissionAttachedEvent::class, PermissionDetachedEvent::class],
            fn () => $this->app->make(AccessEpoch::class)->bump(),
        );
    }
}
