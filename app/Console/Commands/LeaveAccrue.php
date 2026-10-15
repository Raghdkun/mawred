<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Leave\Jobs\UpdateLeaveBalancesJob;
use App\Domain\People\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('leave:accrue {--chunk=200 : Employees per queued job}')]
#[Description('Post leave accrual, year-end carry-over and carry-over expiry (idempotent)')]
final class LeaveAccrue extends Command
{
    public function handle(): int
    {
        Employee::query()
            ->whereNull('termination_date')
            ->orWhere('termination_date', '>=', now()->subYears(2)->startOfYear()->toDateString())
            ->select('id')
            ->chunkById(max(1, (int) $this->option('chunk')), /** @param Collection<int, Employee> $employees */ function (Collection $employees): void {
                $ids = [];

                foreach ($employees as $employee) {
                    $ids[] = $employee->id;
                }

                dispatch(new UpdateLeaveBalancesJob($ids));
            });

        return self::SUCCESS;
    }
}
