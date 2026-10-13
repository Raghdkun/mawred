<?php

declare(strict_types=1);

namespace App\Domain\Access\Contracts;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Grant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Org-relative scope predicates (direct reports, team tree, department, branch). They need the
 * People and Organization domains, so Access only owns the contract; PeopleServiceProvider binds
 * EmployeeScopePredicates.
 */
interface OrgScopePredicates
{
    /**
     * Constrain `$query` so `$employeeColumn` only matches employees visible through `$grant`.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public function constrainEmployees(Builder $query, string $employeeColumn, User $user, Grant $grant): void;

    /**
     * Constrain `$query` so `$userColumn` only matches users whose employee record is visible
     * through `$grant`.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public function constrainUsers(Builder $query, string $userColumn, User $user, Grant $grant): void;

    /**
     * Constrain `$query` to the employee records belonging to `$user` (the Own scope).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public function constrainOwnEmployee(Builder $query, string $employeeColumn, User $user): void;
}
