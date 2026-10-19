<?php

declare(strict_types=1);

namespace App\Domain\People\Events;

use App\Domain\People\Models\Employee;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone's current branch, department, position, employment type or status changed (on the
 * day a placement takes effect). Training re-checks which mandatory courses apply to them.
 */
final readonly class EmployeePlacementChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Employee $employee) {}
}
