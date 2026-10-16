<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\WorkHoursValue;
use App\Domain\Policies\Enums\DayKind;
use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\PolicyResolver;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;

/**
 * The expected schedule for one person and branch-local date (D68): their published roster
 * shifts if they have any; otherwise approved leave, a public holiday or their weekend mean
 * nothing is expected; otherwise the standard work hours from policy.
 */
final readonly class ExpectedSchedule
{
    public function __construct(
        private EmployeeDays $days,
        private BranchHolidays $holidays,
        private PolicyResolver $resolver,
    ) {}

    public function for(Employee $employee, string $date): ExpectedDay
    {
        $branch = $this->days->branch($employee);
        $timezone = $branch instanceof Branch ? $branch->timezone : FieldSpec::string(config('app.timezone'), 'UTC');
        $isHoliday = $branch instanceof Branch && $this->holidays->isHoliday($branch, $date);
        $isWeekend = $this->days->workingDates($employee, $date, $date) === [] && ! $isHoliday;
        $kind = $isHoliday ? DayKind::Holiday : ($isWeekend ? DayKind::Weekend : DayKind::Workday);

        $leave = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->get();

        if ($leave->contains(fn (LeaveRequest $l): bool => $l->kind === LeaveRequestKind::Leave && $l->half_day === null)) {
            return new ExpectedDay('none', 'leave', null, null, 0, $kind, $timezone);
        }

        if ($leave->contains(fn (LeaveRequest $l): bool => $l->kind === LeaveRequestKind::DayOff)) {
            return new ExpectedDay('none', 'day_off', null, null, 0, $kind, $timezone);
        }

        $excused = [];

        foreach ($leave as $l) {
            if ($l->kind === LeaveRequestKind::Hourly && $l->from_time !== null && $l->to_time !== null) {
                $excused[] = [RosterShift::minutes($l->from_time), RosterShift::minutes($l->to_time)];
            }
        }

        $shifts = RosterShift::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('status', ShiftStatus::Published->value)
            ->oldest('starts_at')
            ->get();

        if ($shifts->isNotEmpty()) {
            $start = null;
            $end = null;
            $paid = 0;

            foreach ($shifts as $shift) {
                $span = $shift->span();
                $start = $start === null ? $span[0] : min($start, $span[0]);
                $end = $end === null ? $span[1] : max($end, $span[1]);
                $paid += $shift->paidMinutes();
            }

            return new ExpectedDay('shift', null, $start, $end, $paid, $kind, $timezone, $excused);
        }

        if ($isHoliday) {
            return new ExpectedDay('none', 'holiday', null, null, 0, $kind, $timezone);
        }

        if ($isWeekend) {
            return new ExpectedDay('none', 'weekend', null, null, 0, $kind, $timezone);
        }

        $hours = $this->resolver->value($employee, $date, 'work.hours');
        $hours = $hours instanceof WorkHoursValue ? $hours : new WorkHoursValue('09:00', '17:00');
        $start = RosterShift::minutes($hours->startsAt);
        $end = RosterShift::minutes($hours->endsAt);
        $end = $end <= $start ? $end + 1440 : $end;
        $paid = max(0, $end - $start - $hours->breakMinutes);

        // A half day of leave halves the expected day: the morning or the afternoon is excused.
        $half = $leave->first(fn (LeaveRequest $l): bool => $l->kind === LeaveRequestKind::Leave && $l->half_day !== null);

        if ($half instanceof LeaveRequest) {
            $middle = intdiv($start + $end, 2);
            [$start, $end] = $half->half_day === 'morning' ? [$middle, $end] : [$start, $middle];
            $paid = intdiv($paid, 2);
        }

        return new ExpectedDay('policy', null, $start, $end, $paid, $kind, $timezone, $excused);
    }
}
