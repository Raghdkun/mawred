<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAllowance;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\OvertimeMinutes;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The monthly payroll-input export for one branch (D7, D116): per person, the working days they
 * were employed, absences, lateness, unpaid leave, overtime by kind of day with its weighted
 * hours, and the allowances in force with what started, changed or ended in the month. Amounts
 * appear only for people who can read salaries; payroll itself stays outside Mawred.
 */
final class PayrollInputReport extends BaseReport
{
    private const array ALLOWANCE_LABELS = [
        'housing' => ['en' => 'Housing', 'ar' => 'سكن'],
        'transport' => ['en' => 'Transport', 'ar' => 'مواصلات'],
        'food' => ['en' => 'Food', 'ar' => 'طعام'],
        'phone' => ['en' => 'Phone', 'ar' => 'هاتف'],
        'education' => ['en' => 'Education', 'ar' => 'تعليم'],
        'other' => ['en' => 'Other', 'ar' => 'أخرى'],
    ];

    public function __construct(ScopeResolver $resolver, private readonly EmployeeDays $days)
    {
        parent::__construct($resolver);
    }

    public function key(): string
    {
        return 'payroll.input';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Payroll;
    }

    public function title(): array
    {
        return ['en' => 'Payroll input', 'ar' => 'مدخلات الرواتب'];
    }

    public function description(): array
    {
        return ['en' => 'Monthly attendance, unpaid leave, overtime and allowance changes for payroll.', 'ar' => 'الحضور الشهري والإجازات غير المدفوعة والعمل الإضافي وتغييرات البدلات لإعداد الرواتب.'];
    }

    public function permission(): string
    {
        return 'attendance.export';
    }

    public function filters(): array
    {
        return [new ReportFilter('month', FilterType::Month), new ReportFilter('branch', FilterType::Branch, required: true), new ReportFilter('department', FilterType::Department)];
    }

    public function columns(): array
    {
        $minutes = fn (string $key, string $en, string $ar): ReportColumn => ReportColumn::number($key, $en, $ar, ColumnType::Minutes);

        return [
            ReportColumn::text('number', 'Employee no.', 'الرقم الوظيفي'),
            ReportColumn::text('name', 'Name', 'الاسم'),
            ReportColumn::number('working_days', 'Working days', 'أيام العمل'),
            ReportColumn::number('absent_days', 'Absent days', 'أيام الغياب'),
            $minutes('late_minutes', 'Late', 'التأخير'),
            $minutes('early_minutes', 'Left early', 'الخروج المبكر'),
            ReportColumn::number('unpaid_leave_days', 'Unpaid leave (days)', 'إجازة غير مدفوعة (أيام)', ColumnType::Decimal),
            $minutes('overtime_workday', 'Overtime, workdays', 'إضافي أيام العمل'),
            $minutes('overtime_weekend', 'Overtime, weekends', 'إضافي العطلات الأسبوعية'),
            $minutes('overtime_holiday', 'Overtime, holidays', 'إضافي العطلات الرسمية'),
            $minutes('overtime_night', 'Overtime, night', 'الإضافي الليلي'),
            ReportColumn::number('overtime_weighted_hours', 'Overtime weighted hours', 'ساعات الإضافي المرجّحة', ColumnType::Decimal),
            ReportColumn::text('allowances', 'Allowances', 'البدلات'),
            ReportColumn::text('allowance_changes', 'Allowance changes this month', 'تغييرات البدلات هذا الشهر'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        [$start, $end] = [$params->monthStart(), $params->monthEnd()];
        $amounts = $this->resolver->fieldAccess($user, 'employee.salary') instanceof FieldAccess;
        $people = $this->placed($this->scoped(Employee::query(), $user, SubjectBinding::employee('employees.id')), $params)
            ->where('employees.hire_date', '<=', $end)
            ->where(fn (Builder $q) => $q->whereNull('employees.termination_date')->orWhere('employees.termination_date', '>=', $start))
            ->orderBy('employees.employee_number');

        foreach ($people->lazyById(200, 'employees.id', 'id')->chunk(200) as $chunk) {
            $ids = $chunk->pluck('id')->all();
            $attendance = AttendanceDay::query()->whereIn('employee_id', $ids)->whereBetween('date', [$start, $end])->get()->groupBy('employee_id');
            $leave = LeaveRequest::query()->whereIn('employee_id', $ids)
                ->where('status', LeaveRequestStatus::Approved->value)
                ->where('starts_on', '<=', $end)->where('ends_on', '>=', $start)
                ->whereHas('leaveType', fn (Builder $q) => $q->where('is_paid', false))
                ->get()->groupBy('employee_id');
            $allowances = EmployeeAllowance::query()->whereIn('employee_id', $ids)
                ->where('effective_from', '<=', $end)
                ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start))
                ->orderBy('effective_from')->get()->groupBy('employee_id');

            foreach ($chunk as $employee) {
                yield $this->row($employee, $start, $end, $locale, $amounts, $attendance->get($employee->id), $leave->get($employee->id), $allowances->get($employee->id));
            }
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $sum = fn (string $key): int|float => array_sum(array_column($rows, $key));

        return new ReportSummary([
            ['label' => $ar ? 'الموظفون' : 'People', 'value' => count($rows), 'format' => 'number'],
            ['label' => $ar ? 'أيام الغياب' : 'Absent days', 'value' => (int) $sum('absent_days'), 'format' => 'number'],
            ['label' => $ar ? 'إجازة غير مدفوعة (أيام)' : 'Unpaid leave (days)', 'value' => round((float) $sum('unpaid_leave_days'), 2), 'format' => 'decimal'],
            ['label' => $ar ? 'ساعات الإضافي المرجّحة' : 'Overtime weighted hours', 'value' => round((float) $sum('overtime_weighted_hours'), 2), 'format' => 'decimal'],
        ]);
    }

    /**
     * @param  Collection<int, AttendanceDay>|null  $attendance
     * @param  Collection<int, LeaveRequest>|null  $leave
     * @param  Collection<int, EmployeeAllowance>|null  $allowances
     * @return array<string, string|int|float>
     */
    private function row(Employee $employee, string $start, string $end, string $locale, bool $amounts, ?Collection $attendance, ?Collection $leave, ?Collection $allowances): array
    {
        $from = max($start, $employee->hire_date->toDateString());
        $to = min($end, $employee->termination_date?->toDateString() ?? $end);
        $overtime = ['workday' => 0, 'weekend' => 0, 'holiday' => 0, 'night' => 0];
        $weighted = 0.0;

        foreach ($attendance ?? [] as $day) {
            $split = OvertimeMinutes::split($day->overtime_status->value, $day->overtime_minutes, $day->overtime_approved_minutes, $day->overtime_buckets);
            $weighted += $split['weighted'];

            foreach ($split['buckets'] as $bucket => $minutes) {
                $overtime[$bucket] += $minutes;
            }
        }

        return [
            'number' => $employee->employee_number,
            'name' => $employee->displayName($locale),
            'working_days' => $from <= $to ? count($this->days->workingDates($employee, $from, $to)) : 0,
            'absent_days' => $attendance?->filter(fn (AttendanceDay $d): bool => $d->status === DayStatus::Absent)->count() ?? 0,
            'late_minutes' => array_sum(array_map(fn (AttendanceDay $d): int => $d->late_minutes, $attendance?->all() ?? [])),
            'early_minutes' => array_sum(array_map(fn (AttendanceDay $d): int => $d->early_leave_minutes, $attendance?->all() ?? [])),
            'unpaid_leave_days' => round($this->unpaidDays($employee, $leave, $from, $to), 2),
            'overtime_workday' => $overtime['workday'],
            'overtime_weekend' => $overtime['weekend'],
            'overtime_holiday' => $overtime['holiday'],
            'overtime_night' => $overtime['night'],
            'overtime_weighted_hours' => round($weighted, 2),
            'allowances' => $this->allowancesInForce($allowances, $end, $locale, $amounts),
            'allowance_changes' => $this->allowanceChanges($allowances, $start, $end, $locale, $amounts),
        ];
    }

    /**
     * Unpaid leave inside the month: whole-day leave counts its working days in the month (a half
     * day counts half), hourly leave counts its hours over the length of a working day.
     *
     * @param  Collection<int, LeaveRequest>|null  $leave
     */
    private function unpaidDays(Employee $employee, ?Collection $leave, string $from, string $to): float
    {
        $days = 0.0;

        foreach ($leave ?? [] as $request) {
            $starts = max($from, $request->starts_on->toDateString());
            $ends = min($to, $request->ends_on->toDateString());

            if ($starts > $ends) {
                continue;
            }

            if ($request->kind === LeaveRequestKind::Hourly) {
                $days += ($request->minutes ?? 0) / 60 / max(1.0, $this->days->hoursPerDay($employee, $starts));

                continue;
            }

            $working = count($this->days->workingDates($employee, $starts, $ends));
            $days += $request->half_day !== null ? min(0.5, $working) : $working;
        }

        return $days;
    }

    /**
     * @param  Collection<int, EmployeeAllowance>|null  $allowances
     */
    private function allowancesInForce(?Collection $allowances, string $end, string $locale, bool $amounts): string
    {
        return ($allowances ?? new Collection)
            ->filter(fn (EmployeeAllowance $a): bool => $a->coversDate($end))
            ->map(fn (EmployeeAllowance $a): string => $this->describe($a, $locale, $amounts))
            ->implode('; ');
    }

    /**
     * @param  Collection<int, EmployeeAllowance>|null  $allowances
     */
    private function allowanceChanges(?Collection $allowances, string $start, string $end, string $locale, bool $amounts): string
    {
        $ar = $locale === 'ar';
        $changes = [];

        foreach ($allowances ?? [] as $allowance) {
            $from = $allowance->effective_from->toDateString();
            $until = $allowance->effective_to?->toDateString();

            if ($from >= $start && $from <= $end) {
                $changes[] = $this->describe($allowance, $locale, $amounts).' '.($ar ? "من {$from}" : "from {$from}");
            }

            if ($until !== null && $until >= $start && $until <= $end) {
                $changes[] = $this->describe($allowance, $locale, false).' '.($ar ? "حتى {$until}" : "until {$until}");
            }
        }

        return implode('; ', $changes);
    }

    private function describe(EmployeeAllowance $allowance, string $locale, bool $amounts): string
    {
        $label = self::ALLOWANCE_LABELS[$allowance->type->value][$locale === 'ar' ? 'ar' : 'en'];

        if ($allowance->label !== null && $allowance->label !== '') {
            $label .= " ({$allowance->label})";
        }

        return $amounts && $allowance->amount !== null ? "{$label} {$allowance->amount->format($locale)}" : $label;
    }
}
