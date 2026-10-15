<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Attendance\Jobs\CloseAttendanceDaysJob;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('attendance:close {--chunk=200 : Employees per queued job}')]
#[Description('Close yesterday\'s attendance days: absences, missing check-outs and overtime (idempotent)')]
final class AttendanceClose extends Command
{
    public function handle(): int
    {
        Employee::query()
            ->where('status', '!=', EmployeeStatus::Terminated->value)
            ->select('id')
            ->chunkById(max(1, (int) $this->option('chunk')), function (Collection $employees): void {
                $ids = [];

                foreach ($employees as $employee) {
                    $ids[] = $employee->id;
                }

                dispatch(new CloseAttendanceDaysJob($ids));
            });

        return self::SUCCESS;
    }
}
