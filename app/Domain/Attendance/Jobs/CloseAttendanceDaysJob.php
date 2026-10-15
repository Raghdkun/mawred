<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Jobs;

use App\Domain\Attendance\Actions\RecalculateAttendanceDay;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\People\Models\Employee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/**
 * Closes yesterday (each person's branch-local yesterday) for a chunk of people: absences,
 * missing check-outs and overtime become final. Runs hourly so every timezone is closed soon
 * after its midnight; days already closed are skipped.
 */
#[Queue('default')]
#[Tries(3)]
#[Backoff(60)]
final class CloseAttendanceDaysJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $employeeIds
     */
    public function __construct(public readonly array $employeeIds) {}

    public function handle(RecalculateAttendanceDay $recalculate, EmployeeDays $days): void
    {
        Employee::query()->whereKey($this->employeeIds)->each(function (Employee $employee) use ($recalculate, $days): void {
            $yesterday = now($days->branch($employee)->timezone ?? 'UTC')->subDay()->toDateString();

            $closed = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $yesterday)->whereNotNull('closed_at')->exists();

            if (! $closed && $employee->hire_date->toDateString() <= $yesterday && ($employee->termination_date === null || $employee->termination_date->toDateString() >= $yesterday)) {
                $recalculate->handle($employee, $yesterday);
            }
        });
    }
}
