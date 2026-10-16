<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Attendance\Data\AttendanceDayData;
use App\Domain\Attendance\Data\PunchData;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\GeofenceModeValue;
use App\Domain\Policies\Support\PolicyResolver;
use App\Domain\Requests\Models\RequestType;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Check-in (handoff 25): the big button, the branch area status, QR scanning and the offline
 * queue. Punches go to /api/v1/attendance/punches so the queue and the mobile app share them.
 */
final class ClockController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, EmployeeDays $days, PolicyResolver $resolver, ScopeResolver $scopes): Response
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();
        $today = $employee instanceof Employee ? $days->today($employee) : now()->toDateString();
        $branch = $employee instanceof Employee ? $days->branch($employee) : null;
        $mode = $employee instanceof Employee ? $resolver->value($employee, $today, 'attendance.geofence_mode') : null;
        $punches = AttendanceEvent::query()->where('employee_id', $employee->id ?? 0)->whereNull('voided_at')
            ->where('occurred_at', '>=', now()->subDays(2))->latest('occurred_at')->limit(10)->get();
        $day = $employee instanceof Employee ? AttendanceDay::query()->with('employee')->where('employee_id', $employee->id)->whereDate('date', $today)->first() : null;
        $widest = $scopes->for($user)->widestScope('attendance.view');
        $correction = RequestType::query()->where('code', 'ATTENDANCE_CORRECTION')->first();

        return Inertia::render('attendance/clock', [
            'employeeId' => $employee?->ulid,
            'today' => $today,
            'timezone' => $branch instanceof Branch ? $branch->timezone : config('app.timezone'),
            'branch' => $branch instanceof Branch ? [
                'name' => $branch->getTranslations('name'),
                'area' => $branch->geofence_radius_m === null || $branch->geofence_lat === null || $branch->geofence_lng === null ? null : [
                    'latitude' => (float) $branch->geofence_lat, 'longitude' => (float) $branch->geofence_lng, 'radius' => $branch->geofence_radius_m,
                ],
            ] : null,
            'mode' => $mode instanceof GeofenceModeValue ? $mode->mode : 'flag',
            'next' => $punches->first()?->kind->value === 'in' ? 'out' : 'in',
            'punches' => array_values($punches->map(PunchData::fromModel(...))->all()),
            'day' => $day instanceof AttendanceDay ? AttendanceDayData::fromModel($day) : null,
            'correctionTypeId' => $correction instanceof RequestType && $correction->isAvailable() ? $correction->ulid : null,
            'can' => ['board' => $widest instanceof Scope && $widest !== Scope::Own, 'configure' => Gate::allows('attendance.configure')],
        ]);
    }
}
