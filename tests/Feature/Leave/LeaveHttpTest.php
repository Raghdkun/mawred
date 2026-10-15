<?php

declare(strict_types=1);

use App\Domain\Leave\Actions\UpdateLeaveBalances;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveBlackout;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/*
| Phase 5a over HTTP: the live quote, balances and statements, the leave calendar, adjustments,
| cancelling approved leave and blackout periods, with scope-leak checks (branch A ≠ branch B).
*/
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-01 08:00:00');
    Mail::fake();
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();

    $this->a = Branch::factory()->riyadh()->create();
    $this->b = Branch::factory()->riyadh()->create();
    $place = function (Branch $branch, string $role, ?Employee $manager = null): array {
        $user = userWithRoles($role);
        // HR roles must have two-factor on before they reach the app.
        $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

        return [$user, Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $user->id, 'hire_date' => '2024-01-01'])];
    };

    [$this->managerUser, $manager] = $place($this->a, 'employee');
    [$this->hrUser] = $place($this->a, 'hr_officer');
    [$this->userA, $this->employeeA] = $place($this->a, 'employee', $manager);
    [$this->peerUser, $this->peer] = $place($this->a, 'employee', $manager);
    [$this->userB, $this->employeeB] = $place($this->b, 'employee');
    $this->annual = LeaveType::query()->where('code', 'ANNUAL')->sole();
    $this->annualRequest = RequestType::query()->where('code', 'ANNUAL_LEAVE')->sole();

    foreach ([$this->employeeA, $this->peer, $this->employeeB] as $employee) {
        resolve(UpdateLeaveBalances::class)->handle($employee, '2026-03-01');
    }
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @param  array<string, mixed>  $answers
 */
function leaveRequestFor(object $test, object $user, array $answers): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($user), $test->annualRequest, $answers);
}

it('quotes a leave request live, problems included', function (): void {
    Sanctum::actingAs($this->userA);

    $this->postJson("/api/v1/request-types/{$this->annualRequest->ulid}/leave-quote", ['values' => ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-12']]])
        ->assertOk()
        ->assertJsonPath('data.amount', 5)
        ->assertJsonPath('data.available', 8.5)
        ->assertJsonPath('data.after', 3.5)
        ->assertJsonPath('data.problems', []);

    $this->postJson("/api/v1/request-types/{$this->annualRequest->ulid}/leave-quote", ['values' => ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-26']]])
        ->assertOk()
        ->assertJsonPath('data.problems.0.code', 'leave.insufficient');

    $asset = RequestType::query()->where('code', 'ASSET_REQUEST')->sole();
    $this->postJson("/api/v1/request-types/{$asset->ulid}/leave-quote", ['values' => []])->assertNotFound();
});

it('maps a refused leave request onto the dates field', function (): void {
    $this->actingAs($this->userA)
        ->from(route('requests.create', $this->annualRequest))
        ->post(route('requests.store', $this->annualRequest), ['values' => ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-26']]])
        ->assertRedirect(route('requests.create', $this->annualRequest))
        ->assertSessionHasErrors(['dates', 'error']);

    expect(EmployeeRequest::query()->count())->toBe(0);
});

it('shows balances to the person and to HR in their branch, and nobody else', function (): void {
    Sanctum::actingAs($this->userA);
    $this->getJson("/api/v1/employees/{$this->employeeA->ulid}/leave?year=2026")->assertOk()
        ->assertJsonPath('meta.canAdjust', false)
        ->assertJsonPath('data.0.code', 'ANNUAL')
        ->assertJsonPath('data.0.available', 8.5);
    $this->getJson("/api/v1/employees/{$this->peer->ulid}/leave")->assertNotFound();
    $this->getJson("/api/v1/employees/{$this->employeeA->ulid}/leave/{$this->annual->ulid}/statement?year=2026")->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.runningBalance', 8.5);

    Sanctum::actingAs($this->hrUser);
    $this->getJson("/api/v1/employees/{$this->employeeA->ulid}/leave")->assertOk()->assertJsonPath('meta.canAdjust', true);
    $this->getJson("/api/v1/employees/{$this->employeeB->ulid}/leave")->assertNotFound();
    $this->getJson("/api/v1/employees/{$this->employeeB->ulid}/leave/requests")->assertNotFound();
});

it('lets HR adjust a balance in scope, with a reason, and never their own', function (): void {
    Sanctum::actingAs($this->hrUser);
    $this->postJson("/api/v1/employees/{$this->employeeA->ulid}/leave/adjustments", ['leaveType' => $this->annual->ulid, 'year' => 2026, 'amount' => 2.5, 'note' => 'Opening balance'])
        ->assertCreated();
    $this->postJson("/api/v1/employees/{$this->employeeA->ulid}/leave/adjustments", ['leaveType' => $this->annual->ulid, 'year' => 2026, 'amount' => 1])
        ->assertUnprocessable();
    $this->postJson("/api/v1/employees/{$this->employeeB->ulid}/leave/adjustments", ['leaveType' => $this->annual->ulid, 'year' => 2026, 'amount' => 1, 'note' => 'x'])
        ->assertNotFound();

    Sanctum::actingAs($this->userA);
    $this->postJson("/api/v1/employees/{$this->employeeA->ulid}/leave/adjustments", ['leaveType' => $this->annual->ulid, 'year' => 2026, 'amount' => 10, 'note' => 'Please'])
        ->assertForbidden();

    expect((float) LeaveLedgerEntry::query()->where('employee_id', $this->employeeA->id)->where('kind', 'adjustment')->sum('amount'))->toBe(2.5);
});

it('lets HR cancel approved leave and gives the days back', function (): void {
    $request = leaveRequestFor($this, $this->userA, ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-09']]);
    $leave = LeaveRequest::query()->where('request_id', $request->id)->sole();
    $leave->forceFill(['status' => LeaveRequestStatus::Approved])->save();
    LeaveLedgerEntry::query()->create(['employee_id' => $this->employeeA->id, 'leave_type_id' => $this->annual->id, 'year' => 2026, 'kind' => 'debit', 'amount' => -2, 'effective_on' => '2026-03-08', 'leave_request_id' => $leave->id]);

    Sanctum::actingAs($this->userA);
    $this->postJson("/api/v1/leave/requests/{$leave->ulid}/cancel", ['reason' => 'Changed my mind'])->assertForbidden();

    Sanctum::actingAs($this->hrUser);
    $this->postJson("/api/v1/leave/requests/{$leave->ulid}/cancel", ['reason' => 'Trip called off'])->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect((float) LeaveLedgerEntry::query()->where('leave_request_id', $leave->id)->sum('amount'))->toBe(0.0);
});

it('shows the calendar within scope, with holidays, in a bounded number of queries', function (): void {
    leaveRequestFor($this, $this->userA, ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-09']]);
    leaveRequestFor($this, $this->peerUser, ['dates' => ['from' => '2026-03-10', 'to' => '2026-03-10']]);
    leaveRequestFor($this, $this->userB, ['dates' => ['from' => '2026-03-10', 'to' => '2026-03-11']]);
    $calendar = fn () => $this->getJson('/api/v1/leave/calendar?from=2026-03-01&to=2026-03-31')->assertOk();

    Sanctum::actingAs($this->hrUser);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $names = collect($calendar()->json('data'))->pluck('employeeName')->sort()->values()->all();
    expect($names)->toBe(collect([$this->employeeA->name_en, $this->peer->name_en])->sort()->values()->all())
        ->and($queries)->toBeLessThan(20);

    Sanctum::actingAs($this->userA);
    expect($calendar()->json('data.*.employeeId'))->toBe([$this->employeeA->ulid]);

    Sanctum::actingAs($this->hrUser);
    $this->getJson("/api/v1/leave/calendar?from=2026-03-01&to=2026-03-31&branch={$this->b->ulid}")->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/leave/calendar?from=2026-01-01&to=2026-06-30')->assertStatus(422);
});

it('lets leave admins manage blackout periods', function (): void {
    $admin = userWithRoles('hr_admin');
    $admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $payload = ['name' => ['en' => 'Stock count', 'ar' => 'الجرد'], 'startsOn' => '2026-03-22', 'endsOn' => '2026-03-26', 'branchId' => $this->a->ulid, 'departmentId' => null, 'leaveTypeIds' => [$this->annual->ulid]];

    $this->actingAs($this->userA)->get(route('leave.blackouts.index'))->assertForbidden();
    $this->actingAs($this->userA)->post(route('leave.blackouts.store'), $payload)->assertForbidden();

    $this->actingAs($admin)->post(route('leave.blackouts.store'), $payload)->assertRedirect();
    $blackout = LeaveBlackout::query()->sole();
    expect($blackout->branch_id)->toBe($this->a->id)->and($blackout->leave_type_ids)->toBe([$this->annual->id]);

    $this->actingAs($admin)->get(route('leave.blackouts.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('leave/blackouts')->has('blackouts', 1)->where('blackouts.0.name.en', 'Stock count'));
    $this->actingAs($admin)->put(route('leave.blackouts.update', $blackout), [...$payload, 'endsOn' => '2026-03-20'])->assertSessionHasErrors('endsOn');
    $this->actingAs($admin)->delete(route('leave.blackouts.destroy', $blackout))->assertRedirect();
    expect(LeaveBlackout::query()->count())->toBe(0);
});

it('renders my leave, the calendar, the profile tab, home balances and leave on the request', function (): void {
    $this->actingAs($this->userA)->get(route('leave.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('leave/index')->where('employeeId', $this->employeeA->ulid)->where('year', 2026));
    $this->actingAs($this->userA)->get(route('leave.calendar'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('leave/calendar')->where('branch.id', $this->a->ulid));
    $this->actingAs($this->userA)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('balances.0.code', 'ANNUAL')->where('balances.0.available', 8.5));
    $this->actingAs($this->hrUser)->get(route('people.show', $this->employeeA))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.leave', true));

    $request = leaveRequestFor($this, $this->userA, ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-10']]);
    $this->actingAs($this->userA)->get(route('requests.show', $request))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('leave.event.amount', 3)->where('leave.balance.available', 5.5));
});
