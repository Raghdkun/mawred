<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\HiringScope;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Data\DashboardKpiData;
use App\Domain\Reports\Data\HrDashboardData;
use App\Domain\Reports\Support\Instants;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportRegistry;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Support\Time\LocalClock;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * The HR admin home (handoff 6, D115, D123), scoped to the viewer like the reports it links to:
 * KPI cards with 12-month sparklines, documents expiring soon, attendance today by branch on
 * each branch's own date, and integration health.
 */
final readonly class HrDashboard
{
    public const int EXPIRING_DAYS = 30;

    public const int BRANCHES = 10;

    public function __construct(
        private ScopeResolver $resolver,
        private HiringScope $hiring,
        private LocalClock $clock,
        private ReportRegistry $registry,
        private ParamResolver $params,
    ) {}

    public function build(User $user, string $locale): HrDashboardData
    {
        $today = $this->clock->today(FieldSpec::string(config('app.timezone'), 'UTC'));
        $months = MonthWindows::last12($today);
        [$headcount, $turnover] = $this->can($user, 'people.view') ? $this->headcount($user, $months) : [null, null];

        return new HrDashboardData(
            kpis: array_values(array_filter([
                $headcount,
                $turnover,
                $this->can($user, 'hiring.view') ? $this->openPositions($user, $months) : null,
                $this->can($user, 'requests.view') ? $this->waitingRequests($user, $months) : null,
            ])),
            expiring: $this->can($user, 'people.view') ? $this->expiring($user, $locale) : null,
            attendance: $this->can($user, 'attendance.view') ? $this->attendanceToday($user, $locale) : null,
            integrations: $this->can($user, 'integrations.view') ? $this->integrations() : null,
        );
    }

    /**
     * Headcount at each month end, and monthly turnover with the rolling 12-month rate.
     *
     * @param  list<array{0: string, 1: string}>  $months
     * @return array{0: DashboardKpiData, 1: DashboardKpiData}
     */
    private function headcount(User $user, array $months): array
    {
        $query = $this->resolver->apply(Employee::query(), $user, 'people.view', SubjectBinding::employee('employees.id'))->toBase();

        foreach ($months as $i => [$start, $end]) {
            $query->selectRaw('count(*) filter (where employees.hire_date <= ? and (employees.termination_date is null or employees.termination_date >= ?)) as '.MonthWindows::ALIASES[$i], [$end, $end]);
            $query->selectRaw('count(*) filter (where employees.termination_date between ? and ?) as '.MonthWindows::SECOND[$i], [$start, $end]);
        }

        $row = (array) $query->first();
        $heads = MonthWindows::counts($row);
        $leavers = MonthWindows::counts($row, MonthWindows::SECOND);
        $monthly = array_map(fn (int $left, int $head): float => $head > 0 ? round($left * 100 / $head, 1) : 0.0, $leavers, $heads);
        $average = array_sum($heads) / 12;

        return [
            new DashboardKpiData('headcount', $heads[11], 'number', $heads, $heads[11] - $heads[10], $this->href($user, 'people.headcount')),
            new DashboardKpiData('turnover', $average > 0 ? round(array_sum($leavers) * 100 / $average, 1) : 0.0, 'percent', $monthly, null, $this->href($user, 'people.turnover')),
        ];
    }

    /**
     * Seats still to fill on open requisitions, with applications received per month.
     *
     * @param  list<array{0: string, 1: string}>  $months
     */
    private function openPositions(User $user, array $months): DashboardKpiData
    {
        $open = $this->resolver->apply(JobRequisition::query(), $user, 'hiring.view', $this->hiring->requisitions())
            ->where('job_requisitions.status', RequisitionStatus::Open->value);
        $seats = (int) (clone $open)->sum('job_requisitions.headcount');
        $hired = JobApplication::query()->whereIn('job_requisition_id', (clone $open)->select('job_requisitions.id'))->where('status', ApplicationStatus::Hired->value)->count();
        $query = $this->resolver->apply(JobApplication::query(), $user, 'hiring.view', $this->hiring->applications())->toBase();

        foreach ($months as $i => [$start, $end]) {
            [$from, $to] = Instants::between($start, $end);
            $query->selectRaw('count(*) filter (where job_applications.applied_at >= ? and job_applications.applied_at < ?) as '.MonthWindows::ALIASES[$i], [$from, $to]);
        }

        $trend = MonthWindows::counts((array) $query->first());

        return new DashboardKpiData('openPositions', max(0, $seats - $hired), 'number', $trend, null, $this->href($user, 'hiring.funnel'));
    }

    /**
     * Requests waiting for a decision now, with requests sent per month.
     *
     * @param  list<array{0: string, 1: string}>  $months
     */
    private function waitingRequests(User $user, array $months): DashboardKpiData
    {
        $query = $this->resolver->apply(EmployeeRequest::query(), $user, 'requests.view', SubjectBinding::employee('requests.requester_employee_id'))->toBase()
            ->selectRaw('count(*) filter (where requests.status in (?, ?)) as waiting', [RequestStatus::InReview->value, RequestStatus::Returned->value]);

        foreach ($months as $i => [$start, $end]) {
            [$from, $to] = Instants::between($start, $end);
            $query->selectRaw('count(*) filter (where requests.submitted_at >= ? and requests.submitted_at < ?) as '.MonthWindows::ALIASES[$i], [$from, $to]);
        }

        $row = (array) $query->first();

        return new DashboardKpiData('waitingRequests', is_numeric($row['waiting'] ?? null) ? (int) $row['waiting'] : 0, 'number', MonthWindows::counts($row), null, $this->href($user, 'requests.turnaround'));
    }

    /**
     * @return array{total: int, rows: list<array{number: string, name: string, branch: string, document: string, expiresOn: string, daysLeft: int}>, href: string|null}|null
     */
    private function expiring(User $user, string $locale): ?array
    {
        $report = $this->registry->find('people.documents_expiring');

        if (! $report instanceof Report) {
            return null;
        }

        $total = 0;
        $rows = [];

        foreach ($report->rows($user, $this->params->resolve($report, ['days' => self::EXPIRING_DAYS]), $locale) as $row) {
            $total++;

            if (count($rows) < 5) {
                $rows[] = [
                    'number' => (string) ($row['number'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'branch' => (string) ($row['branch'] ?? ''),
                    'document' => (string) ($row['document'] ?? ''),
                    'expiresOn' => (string) ($row['expires_on'] ?? ''),
                    'daysLeft' => is_numeric($row['days_left'] ?? null) ? (int) $row['days_left'] : 0,
                ];
            }
        }

        return ['total' => $total, 'rows' => $rows, 'href' => $this->href($user, 'people.documents_expiring', ['days' => self::EXPIRING_DAYS])];
    }

    /**
     * Today's attendance per branch, each on its own local date.
     *
     * @return list<array{branch: string, present: int, late: int, away: int, absent: int, notIn: int}>
     */
    private function attendanceToday(User $user, string $locale): array
    {
        $zones = [];

        foreach (Branch::query()->distinct()->pluck('timezone') as $zone) {
            if (is_string($zone) && $zone !== '') {
                $zones[] = $zone;
            }
        }

        if ($zones === []) {
            return [];
        }

        $names = new Lookups($locale);
        $rows = $this->resolver->apply(AttendanceDay::query(), $user, 'attendance.view', SubjectBinding::employee('attendance_days.employee_id'))
            ->where(function (Builder $scope) use ($zones): void {
                foreach ($zones as $zone) {
                    $scope->orWhere(fn (Builder $q) => $q->where('attendance_days.date', $this->clock->today($zone))
                        ->whereIn('attendance_days.branch_id', Branch::query()->select('id')->where('timezone', $zone)));
                }
            })
            ->toBase()
            ->select('attendance_days.branch_id')
            ->selectRaw("count(*) filter (where attendance_days.status = 'present') as present")
            ->selectRaw("count(*) filter (where attendance_days.status = 'late') as late")
            ->selectRaw("count(*) filter (where attendance_days.status = 'on_leave') as away")
            ->selectRaw("count(*) filter (where attendance_days.status in ('absent', 'incomplete')) as absent")
            ->selectRaw("count(*) filter (where attendance_days.status = 'scheduled') as not_in")
            ->selectRaw("count(*) filter (where attendance_days.status <> 'off') as total")
            ->groupBy('attendance_days.branch_id')
            ->orderByDesc('total')
            ->limit(self::BRANCHES)
            ->get();

        $int = fn (mixed $v): int => is_numeric($v) ? (int) $v : 0;

        return array_values($rows->filter(fn (object $r): bool => $int($r->total ?? 0) > 0)->map(fn (object $r): array => [
            'branch' => $names->branch(is_numeric($r->branch_id ?? null) ? (int) $r->branch_id : null),
            'present' => $int($r->present ?? 0),
            'late' => $int($r->late ?? 0),
            'away' => $int($r->away ?? 0),
            'absent' => $int($r->absent ?? 0),
            'notIn' => $int($r->not_in ?? 0),
        ])->all());
    }

    /**
     * @return list<array{name: string, type: string, status: string, checkedAt: string|null}>
     */
    private function integrations(): array
    {
        return array_values(IntegrationConnection::query()->orderBy('type')->orderBy('name')->get()
            ->map(fn (IntegrationConnection $c): array => ['name' => $c->name, 'type' => $c->type, 'status' => $c->status, 'checkedAt' => $c->health_checked_at?->toIso8601String()])
            ->all());
    }

    private function can(User $user, string $permission): bool
    {
        return $this->resolver->can($user, $permission);
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function href(User $user, string $key, array $query = []): ?string
    {
        $report = $this->registry->find($key);

        return $report instanceof Report && $this->registry->canView($user, $report) ? route('reports.show', ['report' => $key, ...$query]) : null;
    }
}
