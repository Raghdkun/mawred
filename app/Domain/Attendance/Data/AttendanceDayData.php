<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Data;

use App\Domain\Attendance\Models\AttendanceDay;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One person's attendance day (handoffs 25 and 26). Instants are ISO 8601 (UTC). */
#[TypeScript]
final class AttendanceDayData extends Data
{
    /**
     * @param  list<string>  $flags
     */
    public function __construct(
        public string $id,
        public string $employeeId,
        public string $nameEn,
        public ?string $nameAr,
        public string $number,
        public string $date,
        public string $status,
        public string $scheduleSource,
        public ?string $scheduledStart,
        public ?string $scheduledEnd,
        public ?string $firstIn,
        public ?string $lastOut,
        public int $workedMinutes,
        public int $lateMinutes,
        public int $earlyLeaveMinutes,
        public int $overtimeMinutes,
        public string $overtimeStatus,
        public int $overtimeApprovedMinutes,
        public array $flags,
        public bool $corrected,
        public bool $flaggedPunches = false,
    ) {}

    /**
     * Expects `employee` loaded.
     */
    public static function fromModel(AttendanceDay $day, bool $flaggedPunches = false): self
    {
        return new self(
            id: $day->ulid,
            employeeId: $day->employee->ulid ?? '',
            nameEn: $day->employee->name_en ?? '',
            nameAr: $day->employee?->name_ar,
            number: $day->employee->employee_number ?? '',
            date: $day->date->toDateString(),
            status: $day->status->value,
            scheduleSource: $day->schedule_source,
            scheduledStart: $day->scheduled_start?->toIso8601String(),
            scheduledEnd: $day->scheduled_end?->toIso8601String(),
            firstIn: $day->first_in?->toIso8601String(),
            lastOut: $day->last_out?->toIso8601String(),
            workedMinutes: $day->worked_minutes,
            lateMinutes: $day->late_minutes,
            earlyLeaveMinutes: $day->early_leave_minutes,
            overtimeMinutes: $day->overtime_minutes,
            overtimeStatus: $day->overtime_status->value,
            overtimeApprovedMinutes: $day->overtime_approved_minutes,
            flags: $day->flags ?? [],
            corrected: $day->corrected,
            flaggedPunches: $flaggedPunches,
        );
    }
}
