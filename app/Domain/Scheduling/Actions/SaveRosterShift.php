<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Data\RosterShiftInputData;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Support\BranchPlacement;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Adds or changes a shift on a branch roster (create, edit, drag to another person or day). The
 * shift becomes a draft until the roster is published again. Conflicts are shown, not blocked.
 */
final readonly class SaveRosterShift
{
    public function handle(Actor $actor, Branch $branch, RosterShiftInputData $input, ?RosterShift $shift = null): RosterShift
    {
        $employee = $input->employeeId === null ? null : Employee::query()->where('ulid', $input->employeeId)->first();

        if ($input->employeeId !== null && ! $employee instanceof Employee) {
            throw new DomainRuleViolation('scheduling.unknown_employee', __('Choose someone on this roster.'), ['employeeId' => [__('Choose someone on this roster.')]]);
        }

        if ($employee instanceof Employee && ! BranchPlacement::isPlaced($employee, $branch->id, $input->date)) {
            throw new DomainRuleViolation('scheduling.not_in_branch', __('This person isn’t placed at this branch on that date.'), ['employeeId' => [__('This person isn’t placed at this branch on that date.')]]);
        }

        $template = $input->templateId === null ? null : ShiftTemplate::query()->where('ulid', $input->templateId)->first();

        if ($input->templateId !== null && ! $template instanceof ShiftTemplate) {
            throw new DomainRuleViolation('scheduling.unknown_template', __('Choose a shift.'), ['templateId' => [__('Choose a shift.')]]);
        }

        $shift ??= new RosterShift(['created_by' => $actor->user?->id]);
        $shift->fill([
            'branch_id' => $branch->id,
            'employee_id' => $employee?->id,
            'shift_template_id' => $template?->id,
            'position_id' => $input->positionId === null ? null : Position::query()->where('ulid', $input->positionId)->value('id'),
            'date' => $input->date,
            'starts_at' => $input->startsAt ?? $template?->starts_at,
            'ends_at' => $input->endsAt ?? $template?->ends_at,
            'break_minutes' => $input->breakMinutes ?? $template->break_minutes ?? 0,
            'note' => $input->note,
            'auto_assign' => $employee === null && $input->autoAssign,
            'status' => ShiftStatus::Draft,
        ])->save();

        return $shift;
    }
}
