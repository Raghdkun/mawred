<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Leave\Support\LeaveYear;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\LeaveType;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** A manual correction to a balance (opening balances, corrections), always with a reason. */
final readonly class AdjustLeaveBalance
{
    public function __construct(private EmployeeDays $days) {}

    public function handle(Actor $actor, Employee $employee, LeaveType $type, int $year, float $amount, string $note): LeaveLedgerEntry
    {
        if (round($amount, 2) === 0.0 || trim($note) === '') {
            throw new DomainRuleViolation('leave.bad_adjustment', __('Enter a non-zero amount and a reason.'), ['amount' => [__('Enter a non-zero amount and a reason.')]]);
        }

        $today = $this->days->today($employee);
        $entry = LeaveLedgerEntry::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => $year,
            'kind' => LedgerKind::Adjustment,
            'amount' => round($amount, 2),
            'effective_on' => LeaveYear::of($today) === $year ? $today : LeaveYear::start($year),
            'note' => trim($note),
            'actor_user_id' => $actor->user?->id,
        ]);

        activity('leave')->causedBy($actor->user)->performedOn($employee)->event('balance_adjusted')
            ->withProperties(['type' => $type->code, 'year' => $year, 'amount' => round($amount, 2), 'note' => trim($note)])->log('Leave balance adjusted');

        return $entry;
    }
}
