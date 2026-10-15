<?php

declare(strict_types=1);

namespace App\Domain\Leave\Jobs;

use App\Domain\Leave\Actions\UpdateLeaveBalances;
use App\Domain\People\Models\Employee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/** Nightly leave run for a chunk of employees. Idempotent: posts only differences. */
#[Queue('default')]
#[Tries(3)]
#[Backoff(60)]
final class UpdateLeaveBalancesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $employeeIds
     */
    public function __construct(public readonly array $employeeIds) {}

    public function handle(UpdateLeaveBalances $update): void
    {
        Employee::query()->whereKey($this->employeeIds)->each(fn (Employee $employee) => $update->handle($employee));
    }
}
