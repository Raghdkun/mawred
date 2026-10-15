<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Support\LeaveBalances;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\LeaveType;
use Carbon\CarbonImmutable;

/** Takes unused carried-over days off the balance the day after they expire. */
final readonly class ExpireCarryOver
{
    public function __construct(private LeaveBalances $balances) {}

    public function handle(Employee $employee, LeaveType $type, int $year, string $today): void
    {
        $expiring = $this->balances->expiringCarry($employee, $type, $year);

        if ($expiring === null || $expiring['expiresOn'] >= $today) {
            return;
        }

        LeaveLedgerEntry::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => $year,
            'kind' => LedgerKind::CarryExpiry,
            'amount' => -1 * $expiring['amount'],
            'effective_on' => CarbonImmutable::parse($expiring['expiresOn'])->addDay()->toDateString(),
        ]);
    }
}
