<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Access\Contracts\OrgScopePredicates;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Grant;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * Org-relative scopes (ADR-0005), replacing the Phase 0 fail-closed binding:
 *
 * - direct_reports: people whose current manager is the viewer
 * - team_tree: everyone below the viewer in the reporting closure table
 * - department: the anchored departments (or the viewer's own) and their whole subtree
 * - branch: the anchored branches (or the viewer's own)
 *
 * Department and branch membership counts the primary placement and any secondary placement in
 * force (D25). Every org scope also includes the viewer's own record. A viewer without an
 * employee record only sees what explicit anchors grant (D6).
 */
final readonly class EmployeeScopePredicates implements OrgScopePredicates
{
    public function __construct(private ConnectionInterface $db) {}

    public function constrainEmployees(Builder $query, string $employeeColumn, User $user, Grant $grant): void
    {
        $query->whereIn($employeeColumn, $this->visibleEmployeeIds($user, $grant));
    }

    public function constrainUsers(Builder $query, string $userColumn, User $user, Grant $grant): void
    {
        $query->whereIn(
            $userColumn,
            $this->db->table('employees')->select('user_id')->whereNotNull('user_id')->whereIn('id', $this->visibleEmployeeIds($user, $grant)),
        );
    }

    public function constrainOwnEmployee(Builder $query, string $employeeColumn, User $user): void
    {
        $query->whereIn($employeeColumn, $this->ownEmployee($user));
    }

    public function visibleEmployeeIds(User $user, Grant $grant): QueryBuilder
    {
        $own = $this->ownEmployee($user);

        $visible = match ($grant->scope) {
            Scope::Own => null,
            Scope::DirectReports => $this->db->table('employees')->select('id')->whereIn('current_manager_id', $this->ownEmployee($user)),
            Scope::TeamTree => $this->db->table('employee_reporting_paths')->select('descendant_id as id')->whereIn('ancestor_id', $this->ownEmployee($user)),
            Scope::Department => $this->inDepartments($grant->departmentIds === [] ? $this->ownPlacement($user, 'current_department_id') : $grant->departmentIds),
            Scope::Branch => $this->inBranches($grant->branchIds === [] ? $this->ownPlacement($user, 'current_branch_id') : $grant->branchIds),
            Scope::All => $this->db->table('employees')->select('id'),
        };

        return $visible === null ? $own : $own->union($visible);
    }

    /**
     * Departments a Department grant covers: its anchors (or the viewer's own department) and
     * everything below them. Records placed in a department (requisitions) match against it.
     */
    public function departmentIds(User $user, Grant $grant): QueryBuilder
    {
        return $this->subtree($grant->departmentIds === [] ? $this->ownPlacement($user, 'current_department_id') : $grant->departmentIds);
    }

    /**
     * Branches a Branch grant covers: its anchors, or the viewer's own branch.
     *
     * @return list<int>|QueryBuilder
     */
    public function branchIds(User $user, Grant $grant): array|QueryBuilder
    {
        return $grant->branchIds === [] ? $this->ownPlacement($user, 'current_branch_id') : $grant->branchIds;
    }

    /** The viewer's own employee id (empty when they have no employee record). */
    public function ownEmployee(User $user): QueryBuilder
    {
        return $this->db->table('employees')->select('id')->where('user_id', $user->id)->whereNull('deleted_at');
    }

    private function ownPlacement(User $user, string $column): QueryBuilder
    {
        return $this->db->table('employees')->select($column)->where('user_id', $user->id)->whereNull('deleted_at')->whereNotNull($column);
    }

    /**
     * @param  list<int>|QueryBuilder  $roots
     */
    private function inDepartments(array|QueryBuilder $roots): QueryBuilder
    {
        $subtree = $this->subtree($roots);

        return $this->db->table('employees')->select('id')->whereIn('current_department_id', $subtree)
            ->union(
                $this->db->table('employee_assignments')->select('employee_id as id')
                    ->where('is_current', true)
                    ->where('is_primary', false)
                    ->whereIn('department_id', clone $subtree),
            );
    }

    /**
     * @param  list<int>|QueryBuilder  $roots
     */
    private function subtree(array|QueryBuilder $roots): QueryBuilder
    {
        return $this->db->table('departments as d')
            ->select('d.id')
            ->join('departments as root', fn (JoinClause $join) => $join->whereRaw('d.path <@ root.path'))
            ->whereIn('root.id', $roots);
    }

    /**
     * @param  list<int>|QueryBuilder  $branches
     */
    private function inBranches(array|QueryBuilder $branches): QueryBuilder
    {
        return $this->db->table('employees')->select('id')->whereIn('current_branch_id', $branches)
            ->union(
                $this->db->table('employee_assignments')->select('employee_id as id')
                    ->where('is_current', true)
                    ->where('is_primary', false)
                    ->whereIn('branch_id', is_array($branches) ? $branches : clone $branches),
            );
    }
}
