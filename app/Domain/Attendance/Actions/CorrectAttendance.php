<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\GeofenceResult;
use App\Domain\Attendance\Enums\PunchKind;
use App\Domain\Attendance\Enums\PunchSource;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Support\AttendanceClock;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Sets the check-in and/or check-out of a day (handoff 26 correction drawer, or an approved
 * Attendance correction request). The punches it replaces are voided, never edited; the change
 * and its reason go to the audit log.
 */
final readonly class CorrectAttendance
{
    public function __construct(
        private ConnectionInterface $db,
        private AttendanceClock $clock,
        private RecalculateAttendanceDay $recalculate,
    ) {}

    public function handle(Actor $actor, Employee $employee, string $date, ?string $checkIn, ?string $checkOut, string $reason): AttendanceDay
    {
        if (($checkIn === null && $checkOut === null) || trim($reason) === '') {
            throw new DomainRuleViolation('attendance.bad_correction', __('Enter a check-in or check-out time and a reason.'), ['reason' => [__('Enter a check-in or check-out time and a reason.')]]);
        }

        $timezone = $this->clock->timezone($employee);
        $toMinutes = fn (string $time): int => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $in = $checkIn === null ? null : $toMinutes($checkIn);
        $out = $checkOut === null ? null : $toMinutes($checkOut);

        // A check-out earlier than the check-in is the next morning (overnight shift).
        if ($in !== null && $out !== null && $out <= $in) {
            $out += 1440;
        }

        $this->db->transaction(function () use ($actor, $employee, $date, $in, $out, $reason, $timezone): void {
            foreach (['in' => $in, 'out' => $out] as $kind => $minutes) {
                if ($minutes === null) {
                    continue;
                }

                AttendanceEvent::query()->where('employee_id', $employee->id)->whereDate('local_date', $date)->where('kind', $kind)->whereNull('voided_at')
                    ->update(['voided_at' => now()]);
                AttendanceEvent::query()->create([
                    'employee_id' => $employee->id,
                    'branch_id' => $employee->current_branch_id,
                    'kind' => PunchKind::from($kind),
                    'source' => PunchSource::Correction,
                    'occurred_at' => $this->clock->instantAt($date, $minutes, $timezone),
                    'received_at' => now(),
                    'local_date' => $date,
                    'geofence' => GeofenceResult::NotChecked,
                    'reason' => trim($reason),
                    'actor_user_id' => $actor->user?->id,
                ]);
            }

            activity('attendance')->causedBy($actor->user)->performedOn($employee)->event('corrected')
                ->withProperties(['date' => $date, 'in' => $in, 'out' => $out, 'reason' => trim($reason), 'channel' => $actor->channel->value])->log('Attendance corrected');
        });

        $day = $this->recalculate->handle($employee, $date);
        $day->forceFill(['corrected' => true])->save();

        return $day;
    }
}
