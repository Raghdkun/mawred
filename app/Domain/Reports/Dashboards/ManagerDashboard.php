<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Data\DashboardKpiData;
use App\Domain\Reports\Data\ManagerDashboardData;
use App\Domain\Reports\Support\ReportRegistry;
use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;

/**
 * A manager's team today (handoff 5, D123): the people they look after, who's in, late, away or
 * not in yet, who's away, and today's late arrivals, on the manager's own branch date. Everyone
 * here is within the manager's people, attendance and leave scope.
 */
final readonly class ManagerDashboard
{
    public function __construct(
        private ScopeResolver $resolver,
        private EmployeeDays $days,
        private LocalClock $clock,
        private ReportRegistry $registry,
    ) {}

    public function build(User $user, string $locale): ManagerDashboardData
    {
        $me = Employee::query()->where('user_id', $user->id)->first();
        $date = $me instanceof Employee ? $this->days->today($me) : $this->clock->today(FieldSpec::string(config('app.timezone'), 'UTC'));
        $myId = $me instanceof Employee ? $me->id : 0;
        $team = $this->resolver->apply(Employee::query(), $user, 'people.view', SubjectBinding::employee('employees.id'))
            ->where('employees.id', '!=', $myId)
            ->whereIn('employees.status', [EmployeeStatus::Active->value, EmployeeStatus::Pending->value]);
        $days = $this->resolver->apply(AttendanceDay::query(), $user, 'attendance.view', SubjectBinding::employee('attendance_days.employee_id'))
            ->where('attendance_days.employee_id', '!=', $myId);
        $today = (clone $days)->where('attendance_days.date', $date)->toBase()
            ->selectRaw("count(*) filter (where attendance_days.status in ('present', 'late')) as in_today")
            ->selectRaw("count(*) filter (where attendance_days.status = 'late') as late")
            ->selectRaw("count(*) filter (where attendance_days.status = 'scheduled') as not_in")
            ->first();
        $away = $this->resolver->apply(LeaveRequest::query(), $user, 'leave.view', SubjectBinding::employee('leave_requests.employee_id'))
            ->with('employee')
            ->where('leave_requests.employee_id', '!=', $myId)
            ->where('leave_requests.status', LeaveRequestStatus::Approved->value)
            ->where('leave_requests.kind', '!=', LeaveRequestKind::Hourly->value)
            ->where('leave_requests.starts_on', '<=', $date)
            ->where('leave_requests.ends_on', '>=', $date)
            ->oldest('leave_requests.ends_on')
            ->get()
            ->unique('employee_id');
        $late = (clone $days)->with('employee')->where('attendance_days.date', $date)->where('attendance_days.status', DayStatus::Late->value)
            ->orderByDesc('attendance_days.late_minutes')->limit(5)->get();
        $week = CarbonImmutable::parse($date)->subDays(6);
        $lateByDay = (clone $days)->where('attendance_days.status', DayStatus::Late->value)->whereBetween('attendance_days.date', [$week->toDateString(), $date])
            ->toBase()->selectRaw('attendance_days.date::text as day, count(*) as n')->groupByRaw('attendance_days.date')->pluck('n', 'day');
        $trend = [];

        for ($day = $week; $day->lessThanOrEqualTo(CarbonImmutable::parse($date)); $day = $day->addDay()) {
            $value = $lateByDay[$day->toDateString()] ?? 0;
            $trend[] = is_numeric($value) ? (int) $value : 0;
        }

        $int = fn (mixed $v): int => is_numeric($v) ? (int) $v : 0;
        $zone = fn (?int $branchId): string => ($branchId === null ? null : Branch::withTrashed()->find($branchId)?->timezone) ?? FieldSpec::string(config('app.timezone'), 'UTC');

        return new ManagerDashboardData(
            date: $date,
            kpis: [
                new DashboardKpiData('team', $team->count(), 'number', [], null, $this->href($user, 'people.headcount')),
                new DashboardKpiData('lateWeek', array_sum($trend), 'number', $trend, null, $this->href($user, 'time.exceptions')),
            ],
            today: [
                'in' => $int($today->in_today ?? 0),
                'late' => $int($today->late ?? 0),
                'away' => $away->count(),
                'notIn' => $int($today->not_in ?? 0),
            ],
            away: array_values($away->take(8)->map(fn (LeaveRequest $l): array => [
                'id' => $this->person($l->employee, $locale)[0],
                'name' => $this->person($l->employee, $locale)[1],
                'until' => $l->ends_on->toDateString(),
            ])->all()),
            late: array_values($late->map(fn (AttendanceDay $d): array => [
                'id' => $this->person($d->employee, $locale)[0],
                'name' => $this->person($d->employee, $locale)[1],
                'minutes' => $d->late_minutes,
                'firstIn' => $d->first_in?->toIso8601String(),
                'timezone' => $zone($d->branch_id),
            ])->all()),
            attendanceHref: $this->boardVisible($user) ? route('attendance.board') : null,
        );
    }

    /**
     * @return array{0: string, 1: string} the person's id and name
     */
    private function person(?Employee $employee, string $locale): array
    {
        return $employee instanceof Employee ? [$employee->ulid, $employee->displayName($locale)] : ['', ''];
    }

    private function boardVisible(User $user): bool
    {
        $widest = $this->resolver->for($user)->widestScope('attendance.view');

        return $widest instanceof Scope && $widest !== Scope::Own;
    }

    private function href(User $user, string $key): ?string
    {
        $report = $this->registry->find($key);

        return $report instanceof Report && $this->registry->canView($user, $report) ? route('reports.show', $key) : null;
    }
}
