<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Models\Employee;
use App\Domain\People\Support\History;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\ConnectionInterface;

/**
 * Records a leaver. `$lastDay` is the last working day (branch-local); every placement ends on
 * it and the status flips to terminated the day after. Direct reports keep their manager until
 * HR reassigns them (the profile lists them). Offboarding workflows arrive in a later phase.
 */
final readonly class TerminateEmployee
{
    public function __construct(private ConnectionInterface $db, private SyncEmployeePlacement $sync) {}

    public function handle(Actor $actor, Employee $employee, string $lastDay, string $reason): Employee
    {
        if ($lastDay < $employee->hire_date->toDateString()) {
            throw new DomainRuleViolation('people.before_hire', __('The last day cannot be before the hire date.'), ['lastDay' => [__('The last day cannot be before the hire date.')]]);
        }

        return $this->db->transaction(function () use ($actor, $employee, $lastDay, $reason): Employee {
            $employee->assignments()->where('effective_from', '>', $lastDay)->delete();
            $employee->assignments()
                ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $lastDay))
                ->update(['effective_to' => $lastDay]);

            $employee->forceFill(['termination_date' => $lastDay, 'termination_reason' => trim($reason)])->save();
            History::record($actor, $employee, HistoryEvent::Terminated, $lastDay, [], trim($reason));

            return $this->sync->handle($employee->refresh());
        });
    }
}
