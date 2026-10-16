<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\Kiosk;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Scheduling\Actions\PublishRoster;
use App\Domain\Scheduling\Actions\SaveRosterShift;
use App\Domain\Scheduling\Actions\SaveShiftTemplate;
use App\Domain\Scheduling\Data\RosterShiftInputData;
use App\Domain\Scheduling\Data\ShiftTemplateInputData;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/*
| Phase 5b over HTTP: the roster planner, My schedule, check-in, the daily board, kiosks and the
| API, with branch scope leaks (a branch A manager or HR officer never reaches branch B).
| Wednesday 4 March 2026, 07:00 in Riyadh.
*/
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-04 04:00:00');
    Mail::fake();
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();

    $this->a = Branch::factory()->riyadh()->create(['geofence_lat' => '24.713600', 'geofence_lng' => '46.675300', 'geofence_radius_m' => 100]);
    $this->b = Branch::factory()->riyadh()->create();
    $place = function (Branch $branch, string $role): array {
        $user = userWithRoles($role);
        $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

        return [$user, Employee::factory()->placedIn($branch)->create(['user_id' => $user->id, 'hire_date' => '2024-01-01'])];
    };

    [$this->managerUser] = $place($this->a, 'branch_manager');
    [$this->hrUser] = $place($this->a, 'hr_officer');
    [$this->aliceUser, $this->alice] = $place($this->a, 'employee');
    [$this->bobUser, $this->bob] = $place($this->b, 'employee');
    $this->morning = resolve(SaveShiftTemplate::class)->handle(ShiftTemplateInputData::from(['code' => 'AM', 'name' => ['en' => 'Morning'], 'startsAt' => '07:00', 'endsAt' => '15:00']));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('shows the planner for the branches someone can plan, and refuses everyone else', function (): void {
    resolve(SaveRosterShift::class)->handle(Actor::system('test'), $this->a, RosterShiftInputData::from(['date' => '2026-03-04', 'employeeId' => $this->alice->ulid, 'templateId' => $this->morning->ulid]));

    $this->actingAs($this->managerUser)->get(route('roster.show'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('schedule/roster')
            ->where('branch.id', $this->a->ulid)
            ->where('from', '2026-03-01')
            ->where('to', '2026-03-07')
            ->where('drafts', 1)
            ->has('branches', 1)
            ->where('shifts.0.employeeId', $this->alice->ulid));

    $this->actingAs($this->aliceUser)->get(route('roster.show'))->assertForbidden();
});

it('lets a branch manager plan their branch only', function (): void {
    $shift = ['date' => '2026-03-05', 'templateId' => $this->morning->ulid];

    $this->actingAs($this->managerUser)->post(route('roster.shifts.store', $this->a), [...$shift, 'employeeId' => $this->alice->ulid])->assertRedirect();
    $this->actingAs($this->managerUser)->post(route('roster.shifts.store', $this->b), [...$shift, 'employeeId' => $this->bob->ulid])->assertForbidden();
    $this->actingAs($this->managerUser)->post(route('roster.shifts.store', $this->b), $shift)->assertForbidden();
    $this->actingAs($this->managerUser)->post(route('roster.publish', $this->b), ['from' => '2026-03-01', 'to' => '2026-03-07'])->assertForbidden();

    $this->actingAs($this->managerUser)->post(route('roster.publish', $this->a), ['from' => '2026-03-01', 'to' => '2026-03-07'])->assertRedirect();
    expect(RosterShift::query()->sole()->status)->toBe(ShiftStatus::Published);

    $this->actingAs($this->aliceUser)->delete(route('roster.shifts.destroy', RosterShift::query()->sole()))->assertForbidden();
});

it('shows people their published shifts and lets them ask for open ones', function (): void {
    $mine = resolve(SaveRosterShift::class)->handle(Actor::system('test'), $this->a, RosterShiftInputData::from(['date' => '2026-03-08', 'employeeId' => $this->alice->ulid, 'templateId' => $this->morning->ulid]));
    $draft = resolve(SaveRosterShift::class)->handle(Actor::system('test'), $this->a, RosterShiftInputData::from(['date' => '2026-03-09', 'employeeId' => $this->alice->ulid, 'templateId' => $this->morning->ulid]));
    $open = resolve(SaveRosterShift::class)->handle(Actor::system('test'), $this->a, RosterShiftInputData::from(['date' => '2026-03-10', 'templateId' => $this->morning->ulid]));
    resolve(PublishRoster::class)->handle(Actor::system('test'), $this->a, '2026-03-08', '2026-03-08');
    resolve(PublishRoster::class)->handle(Actor::system('test'), $this->a, '2026-03-10', '2026-03-10');

    $this->actingAs($this->aliceUser)->get(route('schedule.mine'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('schedule/mine')->has('shifts', 1)->where('shifts.0.id', $mine->ulid)->has('openShifts', 1));
    $this->actingAs($this->bobUser)->get(route('schedule.mine'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('shifts', 0)->has('openShifts', 0));

    $this->actingAs($this->aliceUser)->post(route('schedule.claim', $open))->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($this->bobUser)->post(route('schedule.claim', $open))->assertSessionHasErrors('error');

    Sanctum::actingAs($this->aliceUser);
    $this->getJson('/api/v1/schedule?from=2026-03-01&to=2026-03-31')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.open_shifts.0.claimed', true);
    expect($draft->fresh()?->status)->toBe(ShiftStatus::Draft);
});

it('checks in over the API with the area, the reason rule and the drift budget', function (): void {
    Sanctum::actingAs($this->aliceUser);

    $this->postJson('/api/v1/attendance/punches', ['kind' => 'in', 'latitude' => 24.7136, 'longitude' => 46.6753, 'accuracy' => 15])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'in')
        ->assertJsonPath('data.geofence', 'inside')
        ->assertJsonPath('meta.day.status', 'present');
    $this->postJson('/api/v1/attendance/punches', ['kind' => 'out', 'latitude' => 24.9, 'longitude' => 46.9])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'attendance.reason_required');
    $this->postJson('/api/v1/attendance/punches', ['kind' => 'out', 'tapped_at' => '2026-02-20T04:00:00Z', 'sent_at' => '2026-03-04T04:00:00Z'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'attendance.too_old');

    $this->getJson('/api/v1/attendance/today')->assertOk()->assertJsonPath('meta.next', 'out')->assertJsonCount(1, 'data');

    $this->actingAs($this->aliceUser)->get(route('attendance.clock'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/clock')->where('next', 'out')->where('branch.area.radius', 100)->where('can.board', false));
});

it('shows the daily board within scope, with corrections and the monthly sheet', function (): void {
    AttendanceEvent::query()->create(['employee_id' => $this->bob->id, 'branch_id' => $this->b->id, 'kind' => 'in', 'source' => 'pwa', 'occurred_at' => now(), 'received_at' => now(), 'local_date' => '2026-03-04', 'geofence' => 'off']);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->actingAs($this->hrUser)->get(route('attendance.board', ['date' => '2026-03-03']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/board')
            ->where('branch.id', $this->a->ulid)
            ->has('days', 3)
            ->where('days', fn ($days): bool => collect($days)->pluck('employeeId')->doesntContain($this->bob->ulid))
            ->where('canCorrect', fn ($ids): bool => collect($ids)->contains($this->alice->ulid)));
    expect($queries)->toBeLessThan(150);

    $this->actingAs($this->hrUser)->get(route('attendance.board', ['date' => '2026-03-04', 'branch' => $this->b->ulid]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('days', 0));

    $this->actingAs($this->hrUser)->post(route('attendance.correct', $this->alice), ['date' => '2026-03-03', 'checkIn' => '09:00', 'checkOut' => '17:00', 'reason' => 'Forgot'])->assertRedirect();
    expect(AttendanceDay::query()->where('employee_id', $this->alice->id)->whereDate('date', '2026-03-03')->sole()->corrected)->toBeTrue();
    $this->actingAs($this->hrUser)->post(route('attendance.correct', $this->bob), ['date' => '2026-03-03', 'checkIn' => '09:00', 'reason' => 'x'])->assertForbidden();
    $this->actingAs($this->aliceUser)->get(route('attendance.board'))->assertForbidden();

    $this->actingAs($this->hrUser)->get(route('attendance.export', ['month' => '2026-03', 'branch' => $this->a->ulid]))->assertOk()->assertDownload('attendance-'.$this->a->code.'-2026-03.xlsx');
});

it('creates kiosks whose secret link shows rotating codes until they are switched off', function (): void {
    $admin = userWithRoles('hr_admin');
    $admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($this->hrUser)->post(route('attendance.kiosks.store'), ['name' => 'Front desk', 'branchId' => $this->a->ulid])->assertForbidden();
    $response = $this->actingAs($admin)->post(route('attendance.kiosks.store'), ['name' => 'Front desk', 'branchId' => $this->a->ulid])->assertRedirect();
    $link = $response->getSession()->get('inertia.flash_data.kioskLink') ?? collect($response->getSession()->all())->flatten()->first(fn ($v): bool => is_string($v) && str_contains($v, '/kiosk/'));
    expect($link)->toBeString();
    $token = basename((string) $link);

    $this->get(route('kiosk.show', $token))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('public/kiosk')->where('name', 'Front desk'));
    $this->getJson(route('kiosk.code', $token))->assertOk()->assertJsonStructure(['code', 'svg', 'refreshIn']);
    $this->get(route('kiosk.show', 'not-a-real-token'))->assertNotFound();

    $kiosk = Kiosk::query()->sole();
    $this->actingAs($admin)->delete(route('attendance.kiosks.destroy', $kiosk))->assertRedirect();
    $this->getJson(route('kiosk.code', $token))->assertNotFound();
});
