<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;

/** Attendance exceptions per person in a period: late, early leave, absent and incomplete days. */
final class AttendanceExceptionsReport extends BaseReport
{
    public function key(): string
    {
        return 'time.exceptions';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Time;
    }

    public function title(): array
    {
        return ['en' => 'Attendance exceptions', 'ar' => 'استثناءات الحضور'];
    }

    public function description(): array
    {
        return ['en' => 'Late arrivals, early departures, absences and missing punches per person.', 'ar' => 'التأخير والخروج المبكر والغياب والبصمات الناقصة لكل موظف.'];
    }

    public function permission(): string
    {
        return 'attendance.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('period', FilterType::Period), ...self::placementFilters()];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('number', 'Employee no.', 'الرقم الوظيفي'),
            ReportColumn::text('name', 'Name', 'الاسم'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::number('late_days', 'Late days', 'أيام التأخير'),
            ReportColumn::number('late_minutes', 'Late', 'التأخير', ColumnType::Minutes),
            ReportColumn::number('early_days', 'Early departures', 'الخروج المبكر'),
            ReportColumn::number('early_minutes', 'Left early', 'مدة الخروج المبكر', ColumnType::Minutes),
            ReportColumn::number('absent_days', 'Absent days', 'أيام الغياب'),
            ReportColumn::number('incomplete_days', 'Missing punches', 'بصمات ناقصة'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        $query = $this->placed($this->scoped(AttendanceDay::query(), $user, SubjectBinding::employee('attendance_days.employee_id')), $params, 'attendance_days.branch_id')
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->whereBetween('attendance_days.date', [$params->from(), $params->to()])
            ->toBase()
            ->select(['employees.employee_number', 'employees.preferred_name', 'employees.name_en', 'employees.name_ar', 'employees.current_branch_id'])
            ->selectRaw('count(*) filter (where attendance_days.late_minutes > 0) as late_days')
            ->selectRaw('coalesce(sum(attendance_days.late_minutes), 0) as late_minutes')
            ->selectRaw('count(*) filter (where attendance_days.early_leave_minutes > 0) as early_days')
            ->selectRaw('coalesce(sum(attendance_days.early_leave_minutes), 0) as early_minutes')
            ->selectRaw('count(*) filter (where attendance_days.status = ?) as absent_days', [DayStatus::Absent->value])
            ->selectRaw('count(*) filter (where attendance_days.status = ?) as incomplete_days', [DayStatus::Incomplete->value])
            ->groupBy('employees.id', 'employees.employee_number', 'employees.preferred_name', 'employees.name_en', 'employees.name_ar', 'employees.current_branch_id')
            ->havingRaw('count(*) filter (where attendance_days.late_minutes > 0 or attendance_days.early_leave_minutes > 0 or attendance_days.status in (?, ?)) > 0', [DayStatus::Absent->value, DayStatus::Incomplete->value])
            ->orderBy('employees.employee_number');

        foreach ($query->cursor() as $row) {
            yield [
                'number' => (string) self::text($row->employee_number),
                'name' => Lookups::person(self::text($row->preferred_name), self::text($row->name_en), self::text($row->name_ar), $locale),
                'branch' => $names->branch(self::int($row->current_branch_id)),
                'late_days' => self::int($row->late_days) ?? 0,
                'late_minutes' => self::int($row->late_minutes) ?? 0,
                'early_days' => self::int($row->early_days) ?? 0,
                'early_minutes' => self::int($row->early_minutes) ?? 0,
                'absent_days' => self::int($row->absent_days) ?? 0,
                'incomplete_days' => self::int($row->incomplete_days) ?? 0,
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $sum = fn (string $key): int => (int) array_sum(array_column($rows, $key));
        $ar = $locale === 'ar';

        return new ReportSummary(
            [
                ['label' => $ar ? 'أيام التأخير' : 'Late days', 'value' => $sum('late_days'), 'format' => 'number'],
                ['label' => $ar ? 'إجمالي التأخير' : 'Total lateness', 'value' => $sum('late_minutes'), 'format' => 'minutes'],
                ['label' => $ar ? 'أيام الغياب' : 'Absent days', 'value' => $sum('absent_days'), 'format' => 'number'],
                ['label' => $ar ? 'بصمات ناقصة' : 'Missing punches', 'value' => $sum('incomplete_days'), 'format' => 'number'],
            ],
            $rows === [] ? null : ['type' => 'stacked', 'x' => 'kind', 'series' => [['key' => 'days', 'label' => $ar ? 'الأيام' : 'Days']], 'data' => [
                ['kind' => $ar ? 'تأخير' : 'Late', 'days' => $sum('late_days')],
                ['kind' => $ar ? 'خروج مبكر' : 'Left early', 'days' => $sum('early_days')],
                ['kind' => $ar ? 'غياب' : 'Absent', 'days' => $sum('absent_days')],
                ['kind' => $ar ? 'بصمات ناقصة' : 'Missing punches', 'days' => $sum('incomplete_days')],
            ]],
        );
    }
}
