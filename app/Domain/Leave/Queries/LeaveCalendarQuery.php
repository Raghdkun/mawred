<?php

declare(strict_types=1);

namespace App\Domain\Leave\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Time away on the leave calendar (handoff 24): pending and approved requests overlapping a date
 * range, for the people the viewer's `leave.view` scope reaches (ADR-0005).
 *
 * @extends ScopedQuery<LeaveRequest>
 */
final class LeaveCalendarQuery extends ScopedQuery
{
    private string $from = '1970-01-01';

    private string $to = '9999-12-31';

    private ?int $branchId = null;

    /** @var list<int>|null */
    private ?array $departmentIds = null;

    public function between(string $from, string $to): self
    {
        $this->from = $from;
        $this->to = $to;

        return $this;
    }

    public function inBranch(?int $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    /**
     * @param  list<int>|null  $departmentIds  a department and the ones under it
     */
    public function inDepartments(?array $departmentIds): self
    {
        $this->departmentIds = $departmentIds;

        return $this;
    }

    protected function permission(): string
    {
        return 'leave.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('leave_requests.employee_id');
    }

    /**
     * @return Builder<LeaveRequest>
     */
    protected function baseQuery(): Builder
    {
        return LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->whereIn('leave_requests.status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->where('leave_requests.starts_on', '<=', $this->to)
            ->where('leave_requests.ends_on', '>=', $this->from)
            ->when($this->branchId !== null || $this->departmentIds !== null, fn (Builder $q) => $q->whereIn('leave_requests.employee_id', fn (QueryBuilder $sub) => $sub
                ->select('id')
                ->from('employees')
                ->when($this->branchId !== null, fn (QueryBuilder $s) => $s->where('current_branch_id', $this->branchId))
                ->when($this->departmentIds !== null, fn (QueryBuilder $s) => $s->whereIn('current_department_id', $this->departmentIds ?? []))))
            ->oldest('leave_requests.starts_on')
            ->orderBy('leave_requests.id');
    }
}
