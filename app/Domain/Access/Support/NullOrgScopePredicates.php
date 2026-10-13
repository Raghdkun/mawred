<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Contracts\OrgScopePredicates;
use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 0 binding: without an org structure, org-relative scopes see nothing (fail closed).
 */
final class NullOrgScopePredicates implements OrgScopePredicates
{
    public function constrainEmployees(Builder $query, string $employeeColumn, User $user, Grant $grant): void
    {
        $query->whereRaw('1 = 0');
    }

    public function constrainUsers(Builder $query, string $userColumn, User $user, Grant $grant): void
    {
        $query->whereRaw('1 = 0');
    }

    public function constrainOwnEmployee(Builder $query, string $employeeColumn, User $user): void
    {
        $query->whereRaw('1 = 0');
    }
}
