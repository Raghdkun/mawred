<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/** HR cancels approved time away (plans changed, recorded in error); taken days come back. */
final readonly class CancelApprovedLeave
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, LeaveRequest $leave, string $reason): void
    {
        if ($leave->status !== LeaveRequestStatus::Approved) {
            throw new DomainRuleViolation('leave.not_approved', __('Only approved leave can be cancelled here.'));
        }

        $this->db->transaction(function () use ($actor, $leave, $reason): void {
            $leave->forceFill(['status' => LeaveRequestStatus::Cancelled])->save();

            $debited = (float) LeaveLedgerEntry::query()->where('leave_request_id', $leave->id)->sum('amount');

            if ($leave->leave_type_id !== null && $debited < 0) {
                LeaveLedgerEntry::query()->create([
                    'employee_id' => $leave->employee_id,
                    'leave_type_id' => $leave->leave_type_id,
                    'year' => $leave->year(),
                    'kind' => LedgerKind::Credit,
                    'amount' => -1 * $debited,
                    'effective_on' => $leave->starts_on->toDateString(),
                    'leave_request_id' => $leave->id,
                    'note' => $reason,
                    'actor_user_id' => $actor->user?->id,
                ]);
            }

            activity('leave')->causedBy($actor->user)->performedOn($leave)->event('cancelled')
                ->withProperties(['reason' => $reason, 'returned' => -1 * $debited])->log('Approved leave cancelled');
        });
    }
}
