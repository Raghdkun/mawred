<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Queries\RosterPeopleQuery;
use Illuminate\Support\Facades\Gate;

/**
 * Who may plan which roster (ADR-0005). A person's shifts need `scheduling.update` over that
 * person; branch-level work (open shifts, publishing, copying, coverage) needs `scheduling.update`
 * over at least one person rostered at the branch, or an unrestricted grant.
 */
final readonly class RosterAccess
{
    public function __construct(private ScopeResolver $resolver) {}

    public function canManageBranch(User $user, Branch $branch, string $on): bool
    {
        if (! Gate::forUser($user)->allows('scheduling.update')) {
            return false;
        }

        return resolve(RosterPeopleQuery::class)->through('scheduling.update')->in($branch->id, $on)->visibleTo($user)->exists();
    }

    public function canManageEmployee(User $user, Employee $employee): bool
    {
        return $this->resolver->allows($user, 'scheduling.update', $employee, SubjectBinding::employee('employees.id'));
    }

    public function canManageShift(User $user, RosterShift $shift): bool
    {
        $employee = $shift->employee_id === null ? null : Employee::withTrashed()->find($shift->employee_id);
        $branch = Branch::withTrashed()->find($shift->branch_id);

        return $employee instanceof Employee
            ? $this->canManageEmployee($user, $employee)
            : $branch instanceof Branch && $this->canManageBranch($user, $branch, $shift->date->toDateString());
    }

    /**
     * Branches whose roster the user can plan.
     *
     * @return list<Branch>
     */
    public function branches(User $user, string $on): array
    {
        if (! Gate::forUser($user)->allows('scheduling.update')) {
            return [];
        }

        return array_values(Branch::query()->where('is_active', true)->orderBy('code')->get()
            ->filter(fn (Branch $branch): bool => $this->canManageBranch($user, $branch, $on))
            ->all());
    }
}
