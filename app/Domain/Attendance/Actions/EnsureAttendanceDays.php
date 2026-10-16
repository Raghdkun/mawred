<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\People\Models\Employee;

/**
 * Makes sure everyone on the daily board has a day for the date (people who haven't punched yet
 * have none until the day closes). Computes only the missing ones.
 */
final readonly class EnsureAttendanceDays
{
    public function __construct(private RecalculateAttendanceDay $recalculate) {}

    /**
     * @param  iterable<Employee>  $employees
     */
    public function handle(iterable $employees, string $date): void
    {
        $ids = [];
        $people = [];

        foreach ($employees as $employee) {
            if ($employee->hire_date->toDateString() <= $date && ($employee->termination_date === null || $employee->termination_date->toDateString() >= $date)) {
                $ids[] = $employee->id;
                $people[] = $employee;
            }
        }

        $existing = AttendanceDay::query()->whereIn('employee_id', $ids)->whereDate('date', $date)->pluck('employee_id')->flip();

        foreach ($people as $employee) {
            if (! $existing->has($employee->id)) {
                $this->recalculate->handle($employee, $date);
            }
        }
    }
}
