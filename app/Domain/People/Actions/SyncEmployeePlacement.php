<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Events\EmployeePlacementChanged;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\People\Support\ReportingLines;
use App\Support\Time\LocalClock;
use Illuminate\Database\ConnectionInterface;

/**
 * Recomputes what is "current" for one employee on their branch-local today: the `is_current`
 * flag on assignments, the denormalised `current_*` columns, the reporting line and the
 * pending → active → terminated status, announcing a change of placement or status. Called after
 * every placement change and by the hourly `people:sync-placements` command, so future-dated
 * changes take effect on their day.
 *
 * With no primary assignment in force (a future hire, or after termination) the `current_*`
 * columns follow the next upcoming primary, else the last one, so the person stays visible to
 * the branch and department that own them.
 */
final readonly class SyncEmployeePlacement
{
    public function __construct(
        private ConnectionInterface $db,
        private LocalClock $clock,
        private ReportingLines $lines,
    ) {}

    public function handle(Employee $employee, ?string $today = null): Employee
    {
        return $this->db->transaction(function () use ($employee, $today): Employee {
            $assignments = $employee->assignments()->orderBy('effective_from')->get();
            $today ??= $this->clock->today($this->timezoneFor($employee, $assignments->first(fn (EmployeeAssignment $a): bool => $a->is_primary)));

            foreach ($assignments as $assignment) {
                $current = $assignment->coversDate($today);

                if ($assignment->is_current !== $current) {
                    EmployeeAssignment::query()->whereKey($assignment->id)->update(['is_current' => $current]);
                    $assignment->is_current = $current;
                }
            }

            $primaries = $assignments->filter(fn (EmployeeAssignment $a): bool => $a->is_primary);
            $placement = $primaries->first(fn (EmployeeAssignment $a): bool => $a->coversDate($today))
                ?? $primaries->first(fn (EmployeeAssignment $a): bool => $a->effective_from->toDateString() > $today)
                ?? $primaries->last();

            $previousManager = $employee->current_manager_id;

            $employee->forceFill([
                'current_branch_id' => $placement?->branch_id,
                'current_department_id' => $placement?->department_id,
                'current_position_id' => $placement?->position_id,
                'current_manager_id' => $placement?->manager_employee_id,
                'current_employment_type_id' => $placement?->employment_type_id,
                'current_grade_id' => $placement?->grade_id,
                'status' => $this->status($employee, $today),
            ]);

            $managerChanged = $employee->isDirty('current_manager_id') || ! $this->hasSelfPath($employee);

            if ($managerChanged && $employee->current_manager_id !== null && $this->lines->wouldCycle($employee, $employee->current_manager_id)) {
                // A future-dated change can only be validated when it lands; keep the old line.
                $employee->current_manager_id = $previousManager;
            }

            $placementChanged = $employee->exists && $employee->isDirty(['current_branch_id', 'current_department_id', 'current_position_id', 'current_employment_type_id', 'status']);
            $employee->save();

            if ($placementChanged) {
                event(new EmployeePlacementChanged($employee));
            }

            if ($managerChanged) {
                $this->lines->move($employee, $employee->current_manager_id);
            }

            return $employee;
        });
    }

    private function status(Employee $employee, string $today): EmployeeStatus
    {
        if ($employee->termination_date !== null && $employee->termination_date->toDateString() < $today) {
            return EmployeeStatus::Terminated;
        }

        if ($employee->status === EmployeeStatus::Terminated) {
            return EmployeeStatus::Terminated;
        }

        if ($employee->hire_date->toDateString() > $today) {
            return EmployeeStatus::Pending;
        }

        return $employee->status === EmployeeStatus::Pending ? EmployeeStatus::Active : $employee->status;
    }

    private function timezoneFor(Employee $employee, ?EmployeeAssignment $primary): string
    {
        $branchId = $employee->current_branch_id ?? $primary?->branch_id;
        $timezone = $branchId === null ? null : Branch::withTrashed()->whereKey($branchId)->value('timezone');

        return is_string($timezone) ? $timezone : config()->string('app.timezone');
    }

    private function hasSelfPath(Employee $employee): bool
    {
        return $this->db->table('employee_reporting_paths')
            ->where('ancestor_id', $employee->id)
            ->where('descendant_id', $employee->id)
            ->exists();
    }
}
