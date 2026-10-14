<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Models\EmployeeAssignment;
use App\Support\Errors\DomainRuleViolation;

final readonly class EndSecondaryAssignment
{
    public function __construct(private SyncEmployeePlacement $sync) {}

    public function handle(EmployeeAssignment $assignment, string $on): EmployeeAssignment
    {
        if ($assignment->is_primary) {
            throw new DomainRuleViolation('people.primary_cannot_end', __('The primary placement changes through a transfer or termination.'));
        }

        if ($on < $assignment->effective_from->toDateString()) {
            $assignment->delete();
        } else {
            $assignment->forceFill(['effective_to' => $on])->save();
        }

        if ($assignment->employee !== null) {
            $this->sync->handle($assignment->employee);
        }

        return $assignment;
    }
}
