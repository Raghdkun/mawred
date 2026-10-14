<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\People\Support\Placements;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * A secondary placement (D25): the person also works in another branch or department for
 * rostering and visibility. It never changes their manager or reporting line.
 */
final readonly class AddSecondaryAssignment
{
    public function __construct(private SyncEmployeePlacement $sync) {}

    public function handle(Actor $actor, Employee $employee, PlacementInputData $input, string $from, ?string $to = null, ?string $reason = null): EmployeeAssignment
    {
        if ($to !== null && $to < $from) {
            throw new DomainRuleViolation('people.dates_reversed', __('The end date must be on or after the start date.'), ['effectiveTo' => [__('The end date must be on or after the start date.')]]);
        }

        $assignment = EmployeeAssignment::query()->create([
            ...Placements::resolve($input),
            'manager_employee_id' => null,
            'employee_id' => $employee->id,
            'is_primary' => false,
            'effective_from' => $from,
            'effective_to' => $to,
            'reason' => $reason,
            'created_by' => $actor->user?->id,
        ]);

        $this->sync->handle($employee);

        return $assignment;
    }
}
