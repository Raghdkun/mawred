<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

use App\Domain\Attendance\Enums\OvertimeStatus;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Forms\Support\FieldSpec;
use App\Support\Spreadsheets\SpreadsheetWriter;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The monthly attendance sheet (XLSX, roadmap 5b): one row per person and day with times,
 * lateness and the overtime that counts (approved or automatic, never more than worked).
 */
final class MonthlySheet
{
    /**
     * @param  Builder<AttendanceDay>  $days
     */
    public static function write(string $path, Builder $days, string $timezone): void
    {
        SpreadsheetWriter::write($path, [
            __('Employee number'), __('Name'), __('Date'), __('Status'), __('Scheduled'), __('Check-in'), __('Check-out'),
            __('Worked (min)'), __('Late (min)'), __('Left early (min)'), __('Overtime (min)'), __('Overtime counted (min)'), __('Corrected'),
        ], self::rows($days, $timezone));
    }

    /**
     * @param  Builder<AttendanceDay>  $days
     * @return Generator<int, list<string|int|null>>
     */
    private static function rows(Builder $days, string $timezone): Generator
    {
        $yes = FieldSpec::string(__('Yes'), 'Yes');

        foreach ($days->cursor() as $day) {
            $counted = in_array($day->overtime_status, [OvertimeStatus::Approved, OvertimeStatus::Automatic], true) ? min($day->overtime_minutes, $day->overtime_approved_minutes) : 0;
            $time = fn (?CarbonImmutable $instant): ?string => $instant?->setTimezone($timezone)->format('H:i');

            yield [
                $day->employee->employee_number ?? '',
                $day->employee->name_en ?? '',
                $day->date->toDateString(),
                $day->status->label(),
                $day->scheduled_start === null ? null : $time($day->scheduled_start).'–'.$time($day->scheduled_end),
                $time($day->first_in),
                $time($day->last_out),
                $day->worked_minutes,
                $day->late_minutes,
                $day->early_leave_minutes,
                $day->overtime_minutes,
                $counted,
                $day->corrected ? $yes : null,
            ];
        }
    }
}
