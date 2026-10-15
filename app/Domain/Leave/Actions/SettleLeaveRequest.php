<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use Illuminate\Database\ConnectionInterface;

/**
 * When a time-away request is decided: approval turns the hold into a debit on the ledger;
 * rejection or withdrawal releases it. A returned request keeps its hold. Idempotent.
 */
final readonly class SettleLeaveRequest
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(EmployeeRequest $request, RequestStatus $status): void
    {
        $this->db->transaction(function () use ($request, $status): void {
            $leave = LeaveRequest::query()->where('request_id', $request->id)->lockForUpdate()->first();

            if (! $leave instanceof LeaveRequest || $leave->status !== LeaveRequestStatus::Pending) {
                return;
            }

            $next = match ($status) {
                RequestStatus::Approved, RequestStatus::Completed => LeaveRequestStatus::Approved,
                RequestStatus::Rejected => LeaveRequestStatus::Rejected,
                RequestStatus::Cancelled => LeaveRequestStatus::Cancelled,
                default => null,
            };

            if ($next === null) {
                return;
            }

            $leave->forceFill(['status' => $next])->save();

            if ($next === LeaveRequestStatus::Approved && $leave->kind === LeaveRequestKind::Leave && $leave->leave_type_id !== null && $leave->amount > 0) {
                LeaveLedgerEntry::query()->create([
                    'employee_id' => $leave->employee_id,
                    'leave_type_id' => $leave->leave_type_id,
                    'year' => $leave->year(),
                    'kind' => LedgerKind::Debit,
                    'amount' => -1 * $leave->amount,
                    'effective_on' => $leave->starts_on->toDateString(),
                    'leave_request_id' => $leave->id,
                ]);
            }
        });
    }
}
