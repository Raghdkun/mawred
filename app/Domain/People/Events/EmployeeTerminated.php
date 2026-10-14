<?php

declare(strict_types=1);

namespace App\Domain\People\Events;

use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Someone is leaving (the offboarding checklist listens). */
final readonly class EmployeeTerminated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Employee $employee, public Actor $actor) {}
}
