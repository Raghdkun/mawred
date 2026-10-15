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
use Illuminate\Database\ConnectionInterface;

/**
 * Brings a year's posted accrual up to what the calculator says was earned by a date. Only the
 * difference is posted, so re-running is harmless and a rule change corrects itself (down as
 * well as up). Anything above the maximum balance is posted back out as a cap line, and given
 * back when a lower accrual means the cap no longer bites.
 */
final readonly class AccrueLeave
{
    public function __construct(private ConnectionInterface $db, private LeaveBalances $balances) {}

    public function handle(Employee $employee, LeaveType $type, int $year, string $asOf): void
    {
        $asOf = min($asOf, LeaveYear::end($year));

        if ($asOf < LeaveYear::start($year)) {
            return;
        }

        $rule = $this->balances->rule($employee, $type, $asOf);
        $target = AccrualCalculator::accrued($rule, $employee->hire_date->toDateString(), LeaveYear::start($year), $asOf, $employee->termination_date?->toDateString())->accrued;

        $this->db->transaction(function () use ($employee, $type, $year, $asOf, $rule, $target): void {
            $entries = LeaveLedgerEntry::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->get(['kind', 'amount']);

            $posted = 0.0;
            $capped = 0.0;
            $balance = 0.0;

            foreach ($entries as $entry) {
                $balance += $entry->amount;
                $posted += $entry->kind === LedgerKind::Accrual ? $entry->amount : 0.0;
                $capped += $entry->kind === LedgerKind::Cap ? $entry->amount : 0.0;
            }

            $delta = round($target - $posted, 2);

            if ($delta === 0.0) {
                return;
            }

            LeaveLedgerEntry::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $year,
                'kind' => LedgerKind::Accrual,
                'amount' => $delta,
                'effective_on' => $asOf,
                'note' => $delta < 0 ? __('Recalculated after a rule change') : null,
            ]);

            $balance += $delta;
            $cap = 0.0;

            if ($delta > 0 && $rule->maxBalance !== null && $balance > $rule->maxBalance) {
                $cap = -1 * min($delta, $balance - $rule->maxBalance);
            } elseif ($delta < 0 && $capped < 0) {
                // Earning less now: give back days the cap took, up to the reduction and the cap.
                $cap = min(-1 * $delta, -1 * $capped, $rule->maxBalance === null ? INF : max(0.0, $rule->maxBalance - $balance));
            }

            if (round($cap, 2) !== 0.0) {
                LeaveLedgerEntry::query()->create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                    'kind' => LedgerKind::Cap,
                    'amount' => round($cap, 2),
                    'effective_on' => $asOf,
                ]);
            }
        });
    }
}
