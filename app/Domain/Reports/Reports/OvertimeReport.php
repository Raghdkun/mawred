<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\OvertimeMinutes;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;

/** Overtime per person in a period: what was worked, what counts, by kind of day and weighted. */
final class OvertimeReport extends BaseReport
{
    public function key(): string
    {
        return 'time.overtime';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Time;
    }

    public function title(): array
    {
        return ['en' => 'Overtime', 'ar' => 'العمل الإضافي'];
    }

    public function description(): array
    {
        return ['en' => 'Overtime worked and counted per person, by kind of day.', 'ar' => 'العمل الإضافي المنجز والمحتسب لكل موظف حسب نوع اليوم.'];
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
        $minutes = fn (string $key, string $en, string $ar): ReportColumn => ReportColumn::number($key, $en, $ar, ColumnType::Minutes);

        return [
            ReportColumn::text('number', 'Employee no.', 'الرقم الوظيفي'),
            ReportColumn::text('name', 'Name', 'الاسم'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            $minutes('worked', 'Overtime worked', 'الإضافي المنجز'),
            $minutes('counted', 'Overtime counted', 'الإضافي المحتسب'),
            $minutes('workday', 'Workdays', 'أيام العمل'),
            $minutes('weekend', 'Weekends', 'العطلات الأسبوعية'),
            $minutes('holiday', 'Holidays', 'العطلات الرسمية'),
            $minutes('night', 'Night', 'الليلي'),
            ReportColumn::number('weighted_hours', 'Weighted hours', 'الساعات المرجّحة', ColumnType::Decimal),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        $query = $this->placed($this->scoped(AttendanceDay::query(), $user, SubjectBinding::employee('attendance_days.employee_id')), $params, 'attendance_days.branch_id')
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->whereBetween('attendance_days.date', [$params->from(), $params->to()])
            ->where('attendance_days.overtime_minutes', '>', 0)
            ->orderBy('employees.employee_number')->orderBy('attendance_days.date')
            ->toBase()
            ->select([
                'employees.id as employee_id', 'employees.employee_number', 'employees.preferred_name', 'employees.name_en', 'employees.name_ar', 'employees.current_branch_id',
                'attendance_days.overtime_status', 'attendance_days.overtime_minutes', 'attendance_days.overtime_approved_minutes', 'attendance_days.overtime_buckets',
            ]);

        $person = null;
        $totals = $this->blank();

        foreach ($query->cursor() as $row) {
            $id = self::int($row->employee_id);

            if ($person !== null && $person['id'] !== $id) {
                yield [...$person['row'], ...$this->rounded($totals)];
                $totals = $this->blank();
            }

            if ($person === null || $person['id'] !== $id) {
                $person = ['id' => $id, 'row' => [
                    'number' => (string) self::text($row->employee_number),
                    'name' => Lookups::person(self::text($row->preferred_name), self::text($row->name_en), self::text($row->name_ar), $locale),
                    'branch' => $names->branch(self::int($row->current_branch_id)),
                ]];
            }

            $worked = self::int($row->overtime_minutes) ?? 0;
            $buckets = is_string($row->overtime_buckets) ? json_decode($row->overtime_buckets, true) : null;
            $day = OvertimeMinutes::split((string) self::text($row->overtime_status), $worked, self::int($row->overtime_approved_minutes) ?? 0, is_array($buckets) ? $buckets : null);
            $totals['worked'] += $worked;
            $totals['counted'] += $day['counted'];
            $totals['weighted_hours'] += $day['weighted'];

            $totals['workday'] += $day['buckets']['workday'];
            $totals['weekend'] += $day['buckets']['weekend'];
            $totals['holiday'] += $day['buckets']['holiday'];
            $totals['night'] += $day['buckets']['night'];
        }

        if ($person !== null) {
            yield [...$person['row'], ...$this->rounded($totals)];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $sum = fn (string $key): int|float => array_sum(array_column($rows, $key));

        return new ReportSummary(
            [
                ['label' => $ar ? 'الإضافي المنجز' : 'Overtime worked', 'value' => (int) $sum('worked'), 'format' => 'minutes'],
                ['label' => $ar ? 'الإضافي المحتسب' : 'Overtime counted', 'value' => (int) $sum('counted'), 'format' => 'minutes'],
                ['label' => $ar ? 'الساعات المرجّحة' : 'Weighted hours', 'value' => round((float) $sum('weighted_hours'), 2), 'format' => 'decimal'],
            ],
            $rows === [] ? null : ['type' => 'bar', 'x' => 'kind', 'series' => [['key' => 'hours', 'label' => $ar ? 'الساعات المحتسبة' : 'Hours counted']], 'data' => [
                ['kind' => $ar ? 'أيام العمل' : 'Workdays', 'hours' => round($sum('workday') / 60, 1)],
                ['kind' => $ar ? 'العطلات الأسبوعية' : 'Weekends', 'hours' => round($sum('weekend') / 60, 1)],
                ['kind' => $ar ? 'العطلات الرسمية' : 'Holidays', 'hours' => round($sum('holiday') / 60, 1)],
                ['kind' => $ar ? 'الليلي' : 'Night', 'hours' => round($sum('night') / 60, 1)],
            ]],
        );
    }

    /**
     * @return array{worked: int, counted: int, workday: int, weekend: int, holiday: int, night: int, weighted_hours: float}
     */
    private function blank(): array
    {
        return ['worked' => 0, 'counted' => 0, 'workday' => 0, 'weekend' => 0, 'holiday' => 0, 'night' => 0, 'weighted_hours' => 0.0];
    }

    /**
     * @param  array{worked: int, counted: int, workday: int, weekend: int, holiday: int, night: int, weighted_hours: float}  $totals
     * @return array{worked: int, counted: int, workday: int, weekend: int, holiday: int, night: int, weighted_hours: float}
     */
    private function rounded(array $totals): array
    {
        $totals['weighted_hours'] = round($totals['weighted_hours'], 2);

        return $totals;
    }
}
