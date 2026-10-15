<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Leave\Support\LeaveYear;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\LeaveType;

/**
 * The nightly run for one employee: finish last year's accrual and carry it over, expire
 * carry-over past its date, and post this year's accrual up to today (their branch's today).
 */
final readonly class UpdateLeaveBalances
{
    public function __construct(
        private EmployeeDays $days,
        private AccrueLeave $accrue,
        private CloseLeaveYear $close,
        private ExpireCarryOver $expire,
    ) {}

    public function handle(Employee $employee, ?string $asOf = null): void
    {
        $today = $asOf ?? $this->days->today($employee);
        $year = LeaveYear::of($today);
        $hired = $employee->hire_date->toDateString();
        $left = $employee->termination_date?->toDateString();

        if ($hired > $today || ($left !== null && $left < LeaveYear::start($year - 1))) {
            return;
        }

        $held = LeaveLedgerEntry::query()->where('employee_id', $employee->id)->whereIn('year', [$year - 1, $year])->distinct()->pluck('leave_type_id')->all();
        $types = LeaveType::withTrashed()
            ->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('eligible_gender')->orWhere('eligible_gender', $employee->gender ?? '')))
            ->orWhereIn('id', $held)
            ->get();

        foreach ($types as $type) {
            if ($hired <= LeaveYear::end($year - 1)) {
                $this->accrue->handle($employee, $type, $year - 1, LeaveYear::end($year - 1));
                $this->close->handle($employee, $type, $year - 1);
            }

            $this->expire->handle($employee, $type, $year, $today);
            $this->accrue->handle($employee, $type, $year, $today);
        }
    }
}
