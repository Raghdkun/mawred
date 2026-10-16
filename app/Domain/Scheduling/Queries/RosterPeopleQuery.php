<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * People on a branch's roster (handoff 22): placed there by their primary or a secondary
 * assignment in force (D25), not terminated, and within the viewer's `scheduling.*` scope.
 *
 * @extends ScopedQuery<Employee>
 */
final class RosterPeopleQuery extends ScopedQuery
{
    private string $permission = 'scheduling.view';

    private int $branchId = 0;

    private string $on = '1970-01-01';

    private string $search = '';

    public function in(int $branchId, string $on): self
    {
        $this->branchId = $branchId;
        $this->on = $on;

        return $this;
    }

    public function through(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function search(string $search): self
    {
        $this->search = trim($search);

        return $this;
    }

    protected function permission(): string
    {
        return $this->permission;
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('employees.id');
    }

    /**
     * @return Builder<Employee>
     */
    protected function baseQuery(): Builder
    {
        return Employee::query()
            ->where('employees.status', '!=', EmployeeStatus::Terminated->value)
            ->where(fn (Builder $q) => $q->where('employees.current_branch_id', $this->branchId)
                ->orWhereIn('employees.id', fn (QueryBuilder $sub) => $sub->select('employee_id')->from('employee_assignments')
                    ->where('branch_id', $this->branchId)
                    ->where('effective_from', '<=', $this->on)
                    ->where(fn (QueryBuilder $w) => $w->whereNull('effective_to')->orWhere('effective_to', '>=', $this->on))))
            ->when($this->search !== '', fn (Builder $q) => $q->whereRaw('employees.search_text ilike ?', ['%'.addcslashes(mb_strtolower($this->search), '%_\\').'%']))
            ->orderBy('employees.name_en')
            ->orderBy('employees.id');
    }
}
