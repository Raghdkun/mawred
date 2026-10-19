<?php

declare(strict_types=1);

namespace App\Domain\Training\Jobs;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\ApplyAudiencesForEmployee;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/** Checks which mandatory courses now apply to someone who joined or moved (D100). */
#[Queue('default')]
#[Tries(3)]
#[Backoff(60)]
final class ApplyAudiencesForEmployeeJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $employeeId) {}

    public function uniqueId(): string
    {
        return (string) $this->employeeId;
    }

    public function handle(ApplyAudiencesForEmployee $apply): void
    {
        $employee = Employee::query()->find($this->employeeId);

        if ($employee instanceof Employee) {
            $apply->handle($employee);
        }
    }
}
