<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Access\Models\User;
use App\Domain\Attendance\Actions\RecordPunch;
use App\Domain\Attendance\Data\AttendanceDayData;
use App\Domain\Attendance\Data\PunchData;
use App\Domain\Attendance\Enums\PunchKind;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\People\Models\Employee;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Check-in and check-out over /api/v1 (handoff 25): the web app (including its offline queue,
 * which sends the device times, D70) and the mobile app use the same endpoint.
 */
final class PunchesController extends Controller
{
    public function store(Request $request, RecordPunch $record): JsonResponse
    {
        $employee = $this->employee($request);
        Gate::authorize('attendance.create');
        $request->validate([
            'kind' => ['required', 'in:in,out'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'between:0,100000'],
            'code' => ['nullable', 'string', 'max:120'],
            'tapped_at' => ['nullable', 'date', 'required_with:sent_at'],
            'sent_at' => ['nullable', 'date', 'required_with:tapped_at'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $location = [];

        if ($request->filled('latitude') && $request->filled('longitude')) {
            $location = ['latitude' => $request->float('latitude'), 'longitude' => $request->float('longitude'), 'accuracy' => $request->filled('accuracy') ? $request->integer('accuracy') : null];
        }

        $event = $record->handle(
            $this->actor($request),
            $employee,
            PunchKind::from($request->string('kind')->toString()),
            $location,
            $request->filled('code') ? $request->string('code')->toString() : null,
            $request->filled('tapped_at') ? ['tapped_at' => $request->string('tapped_at')->toString(), 'sent_at' => $request->string('sent_at')->toString()] : null,
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        );
        $day = AttendanceDay::query()->with('employee')->where('employee_id', $employee->id)->whereDate('date', $event->local_date->toDateString())->first();

        return response()->json(['data' => PunchData::fromModel($event), 'meta' => ['day' => $day instanceof AttendanceDay ? AttendanceDayData::fromModel($day) : null]], 201);
    }

    public function today(Request $request, EmployeeDays $days): JsonResponse
    {
        $employee = $this->employee($request);
        $today = $days->today($employee);
        $punches = AttendanceEvent::query()->where('employee_id', $employee->id)->whereNull('voided_at')->where('occurred_at', '>=', now()->subDays(2))->latest('occurred_at')->limit(20)->get();
        $day = AttendanceDay::query()->with('employee')->where('employee_id', $employee->id)->whereDate('date', $today)->first();

        return response()->json([
            'data' => array_values($punches->map(PunchData::fromModel(...))->all()),
            'meta' => ['today' => $today, 'next' => $punches->first()?->kind->value === 'in' ? 'out' : 'in', 'day' => $day instanceof AttendanceDay ? AttendanceDayData::fromModel($day) : null],
        ]);
    }

    private function employee(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? Employee::query()->where('user_id', $user->id)->first() : null;
        abort_unless($employee instanceof Employee, 404);

        return $employee;
    }

    private function actor(Request $request): Actor
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Actor::user($user, Channel::Api);
    }
}
