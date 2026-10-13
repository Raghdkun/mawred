<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\People\Support\History;
use App\Domain\People\Support\Placements;
use App\Domain\People\Support\ReportingLines;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Transfer / promotion / manager change: closes the primary assignment in force on `$from` the
 * day before and opens a new one from `$from` (branch-local date). Later primaries are refused:
 * cancel or edit those first, so history never forks.
 */
final readonly class ChangePlacement
{
    public function __construct(
        private ConnectionInterface $db,
        private ReportingLines $lines,
        private SyncEmployeePlacement $sync,
    ) {}

    public function handle(Actor $actor, Employee $employee, PlacementInputData $input, string $from, ?string $reason = null): EmployeeAssignment
    {
        $placement = Placements::resolve($input);

        if ($this->lines->wouldCycle($employee, $placement['manager_employee_id'])) {
            throw new DomainRuleViolation(
                'people.reporting_cycle',
                __('Someone cannot report to themselves or to a person in their own team.'),
                ['managerId' => [__('Someone cannot report to themselves or to a person in their own team.')]],
            );
        }

        if ($from < $employee->hire_date->toDateString()) {
            throw new DomainRuleViolation('people.before_hire', __('Changes cannot start before the hire date.'), ['effectiveFrom' => [__('Changes cannot start before the hire date.')]]);
        }

        return $this->db->transaction(function () use ($actor, $employee, $input, $placement, $from, $reason): EmployeeAssignment {
            $primaries = $employee->assignments()->where('is_primary', true)->lockForUpdate()->get();

            if ($primaries->contains(fn (EmployeeAssignment $a): bool => $a->effective_from->toDateString() > $from)) {
                throw new DomainRuleViolation('people.future_change_exists', __('A later change is already scheduled. Edit or cancel it first.'), ['effectiveFrom' => [__('A later change is already scheduled. Edit or cancel it first.')]]);
            }

            $open = $primaries->first(fn (EmployeeAssignment $a): bool => $a->coversDate($from));

            History::record($actor, $employee, HistoryEvent::PlacementChanged, $from, [
                'from' => $open === null ? null : $this->publicPlacement($open),
                'to' => array_filter([
                    'branch' => $input->branchId,
                    'department' => $input->departmentId,
                    'position' => $input->positionId,
                    'manager' => $input->managerId,
                    'employment_type' => $input->employmentTypeId,
                    'grade' => $input->gradeId,
                ], fn (?string $v): bool => $v !== null),
            ], $reason);

            if ($open !== null) {
                if ($open->effective_from->toDateString() === $from) {
                    // Same-day correction: replace rather than leave a zero-length row.
                    $open->fill([...$placement, 'reason' => $reason ?? $open->reason])->save();
                    $this->sync->handle($employee->refresh());

                    return $open;
                }

                $open->forceFill(['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()])->save();
            }

            $assignment = EmployeeAssignment::query()->create([
                ...$placement,
                'employee_id' => $employee->id,
                'is_primary' => true,
                'effective_from' => $from,
                'reason' => $reason,
                'created_by' => $actor->user?->id,
            ]);

            $this->sync->handle($employee->refresh());

            return $assignment;
        });
    }

    /**
     * @return array<string, string>
     */
    private function publicPlacement(EmployeeAssignment $assignment): array
    {
        $assignment->loadMissing(['branch:id,ulid', 'department:id,ulid', 'position:id,ulid', 'manager:id,ulid', 'employmentType:id,ulid', 'grade:id,ulid']);

        return array_filter([
            'branch' => $assignment->branch?->ulid,
            'department' => $assignment->department?->ulid,
            'position' => $assignment->position?->ulid,
            'manager' => $assignment->manager?->ulid,
            'employment_type' => $assignment->employmentType?->ulid,
            'grade' => $assignment->grade?->ulid,
        ], fn (?string $v): bool => $v !== null);
    }
}
