<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\OvertimeStatus;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;

/**
 * A decided Overtime request (D69): approval lets up to the approved hours count for that day
 * (payroll counts the smaller of approved and worked overtime); rejection or withdrawal means
 * none counts. Works for requests raised from punches and for ones asked for in advance.
 */
final readonly class SettleOvertime
{
    public function __construct(private RecalculateAttendanceDay $recalculate) {}

    public function handle(EmployeeRequest $request, RequestStatus $status): void
    {
        if ($status->isOpen()) {
            return;
        }

        $answers = $request->submission()->first()->data ?? [];
        $date = is_string($answers['date'] ?? null) ? $answers['date'] : null;
        $hours = is_numeric($answers['hours'] ?? null) ? (float) $answers['hours'] : 0.0;
        $day = AttendanceDay::query()->where('overtime_request_id', $request->id)->first();

        if (! $day instanceof AttendanceDay && $date !== null) {
            $employee = Employee::query()->find($request->requester_employee_id);
            $day = $employee instanceof Employee ? $this->recalculate->handle($employee, $date) : null;
        }

        if (! $day instanceof AttendanceDay) {
            return;
        }

        $approved = in_array($status, [RequestStatus::Approved, RequestStatus::Completed], true);
        $day->forceFill([
            'overtime_request_id' => $request->id,
            'overtime_status' => $approved ? OvertimeStatus::Approved : OvertimeStatus::Rejected,
            'overtime_approved_minutes' => $approved ? (int) round($hours * 60) : 0,
        ])->save();
    }
}
