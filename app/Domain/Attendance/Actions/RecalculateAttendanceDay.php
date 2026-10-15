<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\OvertimeStatus;
use App\Domain\Attendance\Jobs\RaiseOvertimeRequestJob;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Support\AttendanceCalculator;
use App\Domain\Attendance\Support\AttendanceClock;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\GraceValue;
use App\Domain\Policies\Data\Values\OvertimeValue;
use App\Domain\Policies\Support\PolicyResolver;
use App\Domain\Scheduling\Support\ExpectedSchedule;
use Illuminate\Database\ConnectionInterface;

/**
 * Recomputes one attendance day from its punches and expected schedule (D68). Days before today
 * are closed: no check-in means absent, an open check-in means a missing check-out. Overtime on a
 * closed day waits for approval (an Overtime request is raised) unless the rule needs none (D69).
 */
final readonly class RecalculateAttendanceDay
{
    public function __construct(
        private ConnectionInterface $db,
        private ExpectedSchedule $schedule,
        private AttendanceClock $clock,
        private EmployeeDays $days,
        private PolicyResolver $resolver,
    ) {}

    public function handle(Employee $employee, string $date): AttendanceDay
    {
        $expected = $this->schedule->for($employee, $date);
        $timezone = $expected->timezone;
        $closed = $date < $this->days->today($employee);
        $punches = AttendanceEvent::query()
            ->where('employee_id', $employee->id)
            ->whereDate('local_date', $date)
            ->whereNull('voided_at')
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (AttendanceEvent $e): array => ['kind' => $e->kind->value, 'at' => $this->clock->minutesInto($date, $e->occurred_at, $timezone)])
            ->all();
        $grace = $this->resolver->value($employee, $date, 'attendance.grace');
        $rule = $this->resolver->value($employee, $date, 'overtime.rules');
        $rule = $rule instanceof OvertimeValue ? $rule : new OvertimeValue;
        $result = AttendanceCalculator::day($expected, array_values($punches), $grace instanceof GraceValue ? $grace : new GraceValue, $rule, $closed);
        $overtime = $result->overtime->minutes();

        $day = $this->db->transaction(function () use ($employee, $date, $expected, $timezone, $closed, $result, $overtime, $rule): AttendanceDay {
            $day = AttendanceDay::query()->lockForUpdate()->firstOrNew(['employee_id' => $employee->id, 'date' => $date]);
            $status = $day->exists ? $day->overtime_status : OvertimeStatus::None;
            $approved = $day->exists ? $day->overtime_approved_minutes : 0;

            if ($overtime === 0 && in_array($status, [OvertimeStatus::Automatic, OvertimeStatus::Pending], true) && $day->overtime_request_id === null) {
                [$status, $approved] = [OvertimeStatus::None, 0];
            } elseif ($overtime > 0 && $closed && $status === OvertimeStatus::None) {
                $status = $rule->requiresApproval ? OvertimeStatus::Pending : OvertimeStatus::Automatic;
            }

            if ($status === OvertimeStatus::Automatic) {
                $approved = $overtime;
            }

            $day->fill([
                'branch_id' => $employee->current_branch_id,
                'schedule_source' => $expected->source,
                'scheduled_start' => $expected->start === null ? null : $this->clock->instantAt($date, $expected->start, $timezone),
                'scheduled_end' => $expected->end === null ? null : $this->clock->instantAt($date, $expected->end, $timezone),
                'scheduled_minutes' => $expected->paidMinutes,
                'first_in' => $result->firstIn === null ? null : $this->clock->instantAt($date, $result->firstIn, $timezone),
                'last_out' => $result->lastOut === null ? null : $this->clock->instantAt($date, $result->lastOut, $timezone),
                'worked_minutes' => $result->worked,
                'late_minutes' => $result->late,
                'early_leave_minutes' => $result->earlyLeave,
                'overtime_minutes' => $overtime,
                'overtime_buckets' => $result->overtime->buckets === [] ? null : $result->overtime->buckets,
                'overtime_status' => $status,
                'overtime_approved_minutes' => $approved,
                'status' => $result->status,
                'flags' => $result->flags === [] ? null : $result->flags,
                'closed_at' => $closed ? ($day->closed_at ?? now()) : null,
            ])->save();

            return $day;
        });

        if ($day->overtime_status === OvertimeStatus::Pending && $day->overtime_request_id === null) {
            dispatch(new RaiseOvertimeRequestJob($day->id))->afterCommit();
        }

        return $day;
    }
}
