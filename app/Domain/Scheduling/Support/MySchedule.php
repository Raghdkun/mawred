<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\Scheduling\Data\MyShiftData;
use App\Domain\Scheduling\Enums\ClaimStatus;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\OpenShiftClaim;
use App\Domain\Scheduling\Models\RosterShift;
use Illuminate\Contracts\Database\Query\Builder;

/** Someone's published shifts and the open shifts they could claim (handoff 23, D73). */
final class MySchedule
{
    /**
     * @return list<MyShiftData>
     */
    public static function shifts(Employee $employee, string $from, string $to): array
    {
        return array_values(RosterShift::query()
            ->with(['template', 'branch'])
            ->where('employee_id', $employee->id)
            ->where('status', ShiftStatus::Published->value)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->oldest('starts_at')
            ->get()
            ->map(fn (RosterShift $s): MyShiftData => MyShiftData::fromModel($s))
            ->all());
    }

    /**
     * Published open shifts from today at the person's branches, for their position or anyone.
     *
     * @return list<MyShiftData>
     */
    public static function openShifts(Employee $employee, string $today): array
    {
        $branches = $employee->current_branch_id === null ? [] : [$employee->current_branch_id];

        foreach (EmployeeAssignment::query()->where('employee_id', $employee->id)->where('effective_from', '<=', $today)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))->get(['branch_id']) as $assignment) {
            $branches[] = $assignment->branch_id;
        }

        $claimed = OpenShiftClaim::query()->where('employee_id', $employee->id)->where('status', ClaimStatus::Pending->value)->pluck('roster_shift_id')->flip();

        return array_values(RosterShift::query()
            ->with(['template', 'branch'])
            ->whereNull('employee_id')
            ->where('status', ShiftStatus::Published->value)
            ->whereIn('branch_id', $branches)
            ->where('date', '>=', $today)
            ->where(fn (Builder $q) => $q->whereNull('position_id')->orWhere('position_id', $employee->current_position_id))
            ->orderBy('date')
            ->oldest('starts_at')
            ->limit(50)
            ->get()
            ->map(fn (RosterShift $s): MyShiftData => MyShiftData::fromModel($s, $claimed->has($s->id)))
            ->all());
    }
}
