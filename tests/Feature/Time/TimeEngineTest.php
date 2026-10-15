<?php

declare(strict_types=1);

use App\Domain\Attendance\Actions\CorrectAttendance;
use App\Domain\Attendance\Actions\RecalculateAttendanceDay;
use App\Domain\Attendance\Actions\RecordPunch;
use App\Domain\Attendance\Actions\SaveKiosk;
use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Attendance\Enums\OvertimeStatus;
use App\Domain\Attendance\Enums\PunchKind;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Support\KioskCodes;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Scheduling\Actions\ApplyRotation;
use App\Domain\Scheduling\Actions\AssignOpenShift;
use App\Domain\Scheduling\Actions\ClaimOpenShift;
use App\Domain\Scheduling\Actions\CopyRosterWeek;
use App\Domain\Scheduling\Actions\PublishRoster;
use App\Domain\Scheduling\Actions\SaveRosterShift;
use App\Domain\Scheduling\Actions\SaveRotationPattern;
use App\Domain\Scheduling\Actions\SaveShiftTemplate;
use App\Domain\Scheduling\Data\RosterShiftInputData;
use App\Domain\Scheduling\Data\ShiftTemplateInputData;
use App\Domain\Scheduling\Enums\ClaimStatus;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\ExpectedSchedule;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/*
| Phase 5b engine: rosters (publish, copy, rotation, open shifts, swaps) and attendance (punches,
| geofence, kiosk, offline, days, corrections, overtime). Riyadh: UTC+3, Friday/Saturday weekend.
| Wednesday 4 March 2026 is "today" (07:00 local).
*/
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-04 04:00:00');
    Mail::fake();
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create(['geofence_lat' => '24.713600', 'geofence_lng' => '46.675300', 'geofence_radius_m' => 100]);
    $person = function (string $role, ?Employee $manager = null, ?Department $department = null): array {
        $user = userWithRoles($role);

        return [$user, Employee::factory()->placedIn($this->branch, $department, $manager)->create(['user_id' => $user->id, 'hire_date' => '2024-01-01'])];
    };

    [$this->managerUser, $this->manager] = $person('employee');
    [$this->hrUser] = $person('hr_officer');
    [$this->aliceUser, $this->alice] = $person('employee', $this->manager);
    [$this->bobUser, $this->bob] = $person('employee', $this->manager);
    $this->morning = resolve(SaveShiftTemplate::class)->handle(ShiftTemplateInputData::from(['code' => 'AM', 'name' => ['en' => 'Morning'], 'startsAt' => '07:00', 'endsAt' => '15:00', 'breakMinutes' => 30]));
    $this->night = resolve(SaveShiftTemplate::class)->handle(ShiftTemplateInputData::from(['code' => 'NIGHT', 'name' => ['en' => 'Night'], 'startsAt' => '22:00', 'endsAt' => '06:00', 'breakMinutes' => 30]));
    $this->inside = ['latitude' => 24.7136, 'longitude' => 46.6753, 'accuracy' => 10];
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function rosterShift(object $test, ?Employee $employee, string $date, ?object $template = null): RosterShift
{
    return resolve(SaveRosterShift::class)->handle(Actor::user($test->managerUser), $test->branch, RosterShiftInputData::from([
        'date' => $date, 'employeeId' => $employee?->ulid, 'templateId' => ($template ?? $test->morning)->ulid,
    ]));
}

function punchAt(object $test, Employee $employee, PunchKind $kind, string $utc, array $extra = []): AttendanceEvent
{
    CarbonImmutable::setTestNow($utc);

    return resolve(RecordPunch::class)->handle(Actor::user($test->aliceUser), $employee, $kind, $extra['location'] ?? $test->inside, $extra['code'] ?? null, $extra['offline'] ?? null, $extra['reason'] ?? null);
}

function timeDecide(EmployeeRequest $request, object $user, Decision $decision = Decision::Approved): void
{
    $task = WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
    resolve(WorkflowEngine::class)->decide($task, $decision, $user);
}

it('keeps shifts as drafts until published, then the schedule follows them', function (): void {
    $shift = rosterShift($this, $this->alice, '2026-03-04');
    expect(resolve(ExpectedSchedule::class)->for($this->alice, '2026-03-04')->source)->toBe('policy');

    expect(resolve(PublishRoster::class)->handle(Actor::user($this->managerUser), $this->branch, '2026-03-01', '2026-03-07'))->toBe(1)
        ->and($shift->fresh()?->status)->toBe(ShiftStatus::Published);

    $expected = resolve(ExpectedSchedule::class)->for($this->alice, '2026-03-04');
    expect($expected->source)->toBe('shift')->and([$expected->start, $expected->end, $expected->paidMinutes])->toBe([420, 900, 450])
        ->and(resolve(ExpectedSchedule::class)->for($this->alice, '2026-03-06')->reason)->toBe('weekend');

    resolve(SaveRosterShift::class)->handle(Actor::user($this->managerUser), $this->branch, RosterShiftInputData::from(['date' => '2026-03-04', 'employeeId' => $this->alice->ulid, 'startsAt' => '08:00', 'endsAt' => '16:00']), $shift->fresh());
    expect($shift->fresh()?->status)->toBe(ShiftStatus::Draft);
});

it('copies a week and applies a rotation without overwriting existing shifts', function (): void {
    rosterShift($this, $this->alice, '2026-03-01');
    rosterShift($this, $this->bob, '2026-03-02');
    rosterShift($this, $this->bob, '2026-03-09');

    expect(resolve(CopyRosterWeek::class)->handle(Actor::user($this->managerUser), $this->branch, '2026-03-01', '2026-03-08'))->toBe(1);

    $pattern = resolve(SaveRotationPattern::class)->handle(['en' => '2 on 1 off'], [$this->morning->ulid, $this->night->ulid, null], $this->branch);
    expect(resolve(ApplyRotation::class)->handle(Actor::user($this->managerUser), $this->branch, $pattern, [$this->alice], '2026-03-15', '2026-03-20'))->toBe(4)
        ->and(RosterShift::query()->where('employee_id', $this->alice->id)->whereDate('date', '2026-03-16')->sole()->shift_template_id)->toBe($this->night->id)
        ->and(RosterShift::query()->where('employee_id', $this->alice->id)->whereDate('date', '2026-03-17')->exists())->toBeFalse();
});

it('fills open shifts by claim and manager choice, or by the first claim with auto-assign', function (): void {
    $open = rosterShift($this, null, '2026-03-05');
    resolve(PublishRoster::class)->handle(Actor::user($this->managerUser), $this->branch, '2026-03-05', '2026-03-05');

    $first = resolve(ClaimOpenShift::class)->handle($this->alice, $open->fresh(), '2026-03-04');
    $second = resolve(ClaimOpenShift::class)->handle($this->bob, $open->fresh(), '2026-03-04');
    expect($first->status)->toBe(ClaimStatus::Pending);

    resolve(AssignOpenShift::class)->handle(Actor::user($this->managerUser), $second);
    expect($open->fresh()?->employee_id)->toBe($this->bob->id)
        ->and($first->fresh()?->status)->toBe(ClaimStatus::Declined);
    expect(fn () => resolve(ClaimOpenShift::class)->handle($this->alice, $open->fresh(), '2026-03-04'))->toThrow(DomainRuleViolation::class);

    $auto = resolve(SaveRosterShift::class)->handle(Actor::user($this->managerUser), $this->branch, RosterShiftInputData::from(['date' => '2026-03-08', 'templateId' => $this->morning->ulid, 'autoAssign' => true]));
    resolve(PublishRoster::class)->handle(Actor::user($this->managerUser), $this->branch, '2026-03-08', '2026-03-08');
    resolve(ClaimOpenShift::class)->handle($this->alice, $auto->fresh(), '2026-03-04');
    expect($auto->fresh()?->employee_id)->toBe($this->alice->id);
});

it('swaps shifts once the colleague and the manager approve', function (): void {
    $mine = rosterShift($this, $this->alice, '2026-03-08');
    resolve(PublishRoster::class)->handle(Actor::user($this->managerUser), $this->branch, '2026-03-08', '2026-03-08');

    $request = resolve(SubmitRequest::class)->handle(Actor::user($this->aliceUser), RequestType::query()->where('code', 'SHIFT_SWAP')->sole(), ['colleague' => $this->bob->ulid, 'date' => '2026-03-08']);
    expect(WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole()->assignee_user_id)->toBe($this->bobUser->id);

    timeDecide($request, $this->bobUser);
    timeDecide($request, $this->managerUser);

    expect($request->fresh()?->status)->toBe(RequestStatus::Approved)
        ->and($mine->fresh()?->employee_id)->toBe($this->bob->id);
});

it('records punches inside the area, flags outside ones with a reason and blocks them when asked', function (): void {
    punchAt($this, $this->alice, PunchKind::In, '2026-03-04 05:58:00');
    expect(AttendanceDay::query()->where('employee_id', $this->alice->id)->sole()->status)->toBe(DayStatus::Present);

    $far = ['latitude' => 24.80, 'longitude' => 46.70, 'accuracy' => 10];
    expect(fn () => punchAt($this, $this->bob, PunchKind::In, '2026-03-04 06:00:00', ['location' => $far]))->toThrow(DomainRuleViolation::class);
    expect(punchAt($this, $this->bob, PunchKind::In, '2026-03-04 06:00:00', ['location' => $far, 'reason' => 'Client site'])->flagged)->toBeTrue();

    resolve(SetPolicyValues::class)->handle(Actor::system('test'), PolicySet::query()->sole(), ['attendance.geofence_mode' => ['mode' => 'block']], '2026-03-04');
    expect(fn () => punchAt($this, $this->manager, PunchKind::In, '2026-03-04 06:02:00', ['location' => $far, 'reason' => 'x']))->toThrow(DomainRuleViolation::class);
});

it('accepts kiosk codes and corrects offline punches for the device clock', function (): void {
    ['kiosk' => $kiosk] = resolve(SaveKiosk::class)->handle($this->branch, 'Front desk');
    $code = KioskCodes::code($kiosk->ulid, $kiosk->secret, CarbonImmutable::parse('2026-03-04 06:00:00')->getTimestamp());

    $event = punchAt($this, $this->alice, PunchKind::In, '2026-03-04 06:00:05', ['code' => $code, 'location' => []]);
    expect($event->kiosk_id)->toBe($kiosk->id)->and($event->flagged)->toBeFalse();
    expect(fn () => punchAt($this, $this->bob, PunchKind::In, '2026-03-04 06:05:00', ['code' => $code]))->toThrow(DomainRuleViolation::class);

    // Tapped offline at 06:10 by a phone running 2 minutes fast; sent at 08:00 server time.
    $offline = punchAt($this, $this->bob, PunchKind::In, '2026-03-04 08:00:00', ['offline' => ['tapped_at' => '2026-03-04T06:12:00Z', 'sent_at' => '2026-03-04T08:02:00Z']]);
    expect($offline->occurred_at->toDateTimeString())->toBe('2026-03-04 06:10:00')->and($offline->drift_seconds)->toBe(120)->and($offline->flagged)->toBeFalse();
});

it('closes a day: absent without punches, overtime raised as a request and settled on approval', function (): void {
    resolve(SetPolicyValues::class)->handle(Actor::system('test'), PolicySet::query()->sole(), ['overtime.rules' => ['enabled' => true, 'roundingMinutes' => 15, 'requiresApproval' => true]], '2026-03-01', 'Overtime on');
    punchAt($this, $this->alice, PunchKind::In, '2026-03-04 06:00:00');
    punchAt($this, $this->alice, PunchKind::Out, '2026-03-04 16:00:00');

    CarbonImmutable::setTestNow('2026-03-05 04:00:00');
    $alice = resolve(RecalculateAttendanceDay::class)->handle($this->alice, '2026-03-04');
    $bob = resolve(RecalculateAttendanceDay::class)->handle($this->bob, '2026-03-04');

    // Policy hours 09:00–17:00 (local) less a 60-minute break = 420; worked 09:00–19:00 = 600.
    expect($bob->status)->toBe(DayStatus::Absent)
        ->and($alice->fresh()?->overtime_minutes)->toBe(180)
        ->and($alice->fresh()?->overtime_status)->toBe(OvertimeStatus::Pending);

    $request = EmployeeRequest::query()->findOrFail($alice->fresh()?->overtime_request_id);
    timeDecide($request, $this->managerUser);
    timeDecide($request, $this->hrUser);

    expect($alice->fresh()?->overtime_status)->toBe(OvertimeStatus::Approved)
        ->and($alice->fresh()?->overtime_approved_minutes)->toBe(180);
});

it('corrects a missed check-out directly or through an approved request', function (): void {
    punchAt($this, $this->alice, PunchKind::In, '2026-03-03 06:00:00');
    CarbonImmutable::setTestNow('2026-03-04 04:00:00');
    expect(resolve(RecalculateAttendanceDay::class)->handle($this->alice, '2026-03-03')->status)->toBe(DayStatus::Incomplete);

    $day = resolve(CorrectAttendance::class)->handle(Actor::user($this->hrUser), $this->alice, '2026-03-03', null, '17:00', 'Forgot to check out');
    expect($day->status)->toBe(DayStatus::Present)->and($day->corrected)->toBeTrue()->and($day->worked_minutes)->toBe(480);

    $request = resolve(SubmitRequest::class)->handle(Actor::user($this->bobUser), RequestType::query()->where('code', 'ATTENDANCE_CORRECTION')->sole(), ['date' => '2026-03-03', 'check_in' => '09:00', 'check_out' => '17:00', 'reason' => 'Phone was dead']);
    timeDecide($request, $this->managerUser);
    timeDecide($request, $this->hrUser);

    expect(AttendanceDay::query()->where('employee_id', $this->bob->id)->whereDate('date', '2026-03-03')->sole()->status)->toBe(DayStatus::Present)
        ->and(AttendanceEvent::query()->where('employee_id', $this->bob->id)->where('source', 'correction')->count())->toBe(2);
});

it('counts a punch after midnight towards last night\'s shift', function (): void {
    rosterShift($this, $this->alice, '2026-03-03', $this->night);
    resolve(PublishRoster::class)->handle(Actor::user($this->managerUser), $this->branch, '2026-03-03', '2026-03-03');

    punchAt($this, $this->alice, PunchKind::In, '2026-03-03 19:00:00');
    $out = punchAt($this, $this->alice, PunchKind::Out, '2026-03-04 03:05:00');

    expect($out->local_date->toDateString())->toBe('2026-03-03')
        ->and(AttendanceDay::query()->where('employee_id', $this->alice->id)->whereDate('date', '2026-03-03')->sole()->worked_minutes)->toBe(485);
});
