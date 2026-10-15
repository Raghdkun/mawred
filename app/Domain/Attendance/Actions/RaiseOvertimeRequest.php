<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\OvertimeStatus;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\RequestType;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Overtime found in punches waits for approval (D69): an Overtime request is sent for the
 * person, from the system, and decided by the usual chain. Without the request type (switched
 * off or not ready) the overtime stays pending on the daily board.
 */
final readonly class RaiseOvertimeRequest
{
    public function __construct(private SubmitRequest $submit) {}

    public function handle(AttendanceDay $day): void
    {
        $type = RequestType::query()->where('code', 'OVERTIME')->first();
        $employee = Employee::query()->find($day->employee_id);

        if ($day->overtime_status !== OvertimeStatus::Pending || $day->overtime_request_id !== null || ! $type instanceof RequestType || ! $type->isAvailable() || ! $employee instanceof Employee) {
            return;
        }

        try {
            $request = $this->submit->handle(Actor::system('attendance:overtime'), $type, [
                'date' => $day->date->toDateString(),
                'hours' => round($day->overtime_minutes / 60, 2),
                'reason' => __('Worked past the schedule (from check-ins).', [], 'en'),
            ], $employee);
        } catch (DomainRuleViolation) {
            return;
        }

        $day->forceFill(['overtime_request_id' => $request->id])->save();
    }
}
