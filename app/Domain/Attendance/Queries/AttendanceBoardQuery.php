<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Attendance\Models\AttendanceDay;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attendance days for the daily board and the monthly sheet (handoff 26), within the viewer's
 * `attendance.view` scope (ADR-0005).
 *
 * @extends ScopedQuery<AttendanceDay>
 */
final class AttendanceBoardQuery extends ScopedQuery
{
    private string $from = '1970-01-01';

    private string $to = '9999-12-31';

    private ?int $branchId = null;

    private string $permission = 'attendance.view';

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

    public function through(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    protected function permission(): string
    {
        return $this->permission;
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('attendance_days.employee_id');
    }

    /**
     * @return Builder<AttendanceDay>
     */
    protected function baseQuery(): Builder
    {
        return AttendanceDay::query()
            ->with('employee')
            ->whereBetween('attendance_days.date', [$this->from, $this->to])
            ->when($this->branchId !== null, fn (Builder $q) => $q->where('attendance_days.branch_id', $this->branchId))
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->select('attendance_days.*')
            ->orderBy('attendance_days.date')
            ->orderBy('employees.name_en');
    }
}
