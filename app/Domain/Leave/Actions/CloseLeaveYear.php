<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Support\LeaveBalances;
use App\Domain\Leave\Support\LeaveYear;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Support\Calculators\AccrualCalculator;

/**
 * Year end (D63): what is left of a year moves into the next one, up to the carry-over limit,
 * with its expiry date. Runs once per type and year; the rest of the old year lapses.
 */
final readonly class CloseLeaveYear
{
    public function __construct(private LeaveBalances $balances) {}

    public function handle(Employee $employee, LeaveType $type, int $year): void
    {
        $next = $year + 1;
        $base = LeaveLedgerEntry::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id);

        if ((clone $base)->where('year', $next)->where('kind', LedgerKind::CarryOver->value)->exists()) {
            return;
        }

        $remaining = round((float) (clone $base)->where('year', $year)->sum('amount'), 2);

        if ($remaining <= 0) {
            return;
        }

        $carry = AccrualCalculator::carryOver($this->balances->rule($employee, $type, LeaveYear::start($next)), $remaining, LeaveYear::start($next));

        if ($carry['amount'] <= 0) {
            return;
        }

        LeaveLedgerEntry::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => $next,
            'kind' => LedgerKind::CarryOver,
            'amount' => round($carry['amount'], 2),
            'effective_on' => LeaveYear::start($next),
            'expires_on' => $carry['expiresOn'],
        ]);
    }
}
