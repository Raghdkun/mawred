<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ClaimStatus;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\OpenShiftClaim;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\BranchPlacement;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Someone asks for a published open shift (D73). With auto-assign on, the first claim gets it;
 * otherwise the roster manager picks from the claims.
 */
final readonly class ClaimOpenShift
{
    public function __construct(private ConnectionInterface $db, private AssignOpenShift $assign) {}

    public function handle(Employee $employee, RosterShift $shift, string $today): OpenShiftClaim
    {
        if (! $shift->isOpen() || $shift->status !== ShiftStatus::Published || $shift->date->toDateString() < $today) {
            throw new DomainRuleViolation('scheduling.not_open', __('This shift is no longer open.'));
        }

        if (! BranchPlacement::isPlaced($employee, $shift->branch_id, $shift->date->toDateString())
            || ($shift->position_id !== null && $employee->current_position_id !== $shift->position_id)) {
            throw new DomainRuleViolation('scheduling.not_eligible', __('This open shift is for someone else.'));
        }

        $busy = RosterShift::query()->where('employee_id', $employee->id)->whereDate('date', $shift->date->toDateString())->exists();

        if ($busy) {
            throw new DomainRuleViolation('scheduling.busy', __('You already have a shift that day.'));
        }

        $claim = $this->db->transaction(fn (): OpenShiftClaim => OpenShiftClaim::query()->firstOrCreate(
            ['roster_shift_id' => $shift->id, 'employee_id' => $employee->id],
            ['status' => ClaimStatus::Pending],
        ));

        if ($shift->auto_assign && $claim->status === ClaimStatus::Pending) {
            $this->assign->handle(null, $claim);
        }

        return $claim->refresh();
    }
}
