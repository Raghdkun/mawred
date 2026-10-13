<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Contracts\OrgScopePredicates;
use App\Domain\People\Support\EmployeeScopePredicates;
use Illuminate\Support\ServiceProvider;

/**
 * People wiring: the real org-relative scope predicates (ADR-0005) behind the Access contract.
 */
final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrgScopePredicates::class, EmployeeScopePredicates::class);
    }
}
