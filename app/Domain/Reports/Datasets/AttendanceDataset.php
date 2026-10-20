<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Builder;

/** One row per person and working day: status, lateness, hours and overtime (attendance.view). *
 * @extends BaseDataset<AttendanceDay>
 */
final class AttendanceDataset extends BaseDataset
{
    public function key(): string
    {
        return 'attendance';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Time;
    }

    public function title(): array
    {
        return ['en' => 'Attendance days', 'ar' => 'أيام الحضور'];
    }

    public function description(): array
    {
        return ['en' => 'Each person\'s day: status, lateness, time worked and overtime.', 'ar' => 'يوم كل موظف: الحالة والتأخير ووقت العمل والعمل الإضافي.'];
    }

    public function permission(): string
    {
        return 'attendance.view';
    }

    public function dateField(): string
    {
        return 'date';
    }

    public function branchColumn(): string
    {
        return 'attendance_days.branch_id';
    }

    public function fields(): array
    {
        return [
            ...array_map(
                fn (DatasetField $field): DatasetField => $field->key === 'branch' ? DatasetField::text('branch', 'Branch', 'الفرع', self::translated('rb.name')) : $field,
                self::personFields(),
            ),
            DatasetField::date('date', 'Date', 'التاريخ', 'attendance_days.date'),
            DatasetField::text('month', 'Month', 'الشهر', "to_char(attendance_days.date, 'YYYY-MM')"),
            DatasetField::choice('status', 'Status', 'الحالة', 'attendance_days.status', self::options([
                'scheduled' => ['Not in yet', 'لم يحضر بعد'], 'present' => ['Present', 'حاضر'], 'late' => ['Late', 'متأخر'],
                'absent' => ['Absent', 'غائب'], 'incomplete' => ['Missing check-out', 'بدون انصراف'], 'on_leave' => ['On leave', 'في إجازة'], 'off' => ['Day off', 'يوم راحة'],
            ])),
            DatasetField::number('scheduled_minutes', 'Scheduled', 'المجدول', 'attendance_days.scheduled_minutes', ColumnType::Minutes),
            DatasetField::number('worked_minutes', 'Worked', 'وقت العمل', 'attendance_days.worked_minutes', ColumnType::Minutes),
            DatasetField::number('late_minutes', 'Late', 'التأخير', 'attendance_days.late_minutes', ColumnType::Minutes),
            DatasetField::number('early_leave_minutes', 'Left early', 'الخروج المبكر', 'attendance_days.early_leave_minutes', ColumnType::Minutes),
            DatasetField::number('overtime_minutes', 'Overtime worked', 'الإضافي المنجز', 'attendance_days.overtime_minutes', ColumnType::Minutes),
            DatasetField::number('overtime_counted_minutes', 'Overtime counted', 'الإضافي المحتسب', "case when attendance_days.overtime_status in ('approved', 'auto') then least(attendance_days.overtime_minutes, attendance_days.overtime_approved_minutes) else 0 end", ColumnType::Minutes),
        ];
    }

    protected function base(): Builder
    {
        return AttendanceDay::query()
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->leftJoin('branches as rb', 'rb.id', '=', 'attendance_days.branch_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'employees.current_department_id');
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('attendance_days.employee_id');
    }
}
