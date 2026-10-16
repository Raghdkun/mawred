<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use Illuminate\Contracts\Database\Query\Builder;

/** Whether someone works at a branch on a date: their primary placement or a secondary one (D25). */
final class BranchPlacement
{
    public static function isPlaced(Employee $employee, int $branchId, string $date): bool
    {
        return $employee->current_branch_id === $branchId || EmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('branch_id', $branchId)
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->exists();
    }
}
