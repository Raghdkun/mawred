<?php

declare(strict_types=1);

use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Leave\Actions\AdjustLeaveBalance;
use App\Domain\Leave\Actions\CancelApprovedLeave;
use App\Domain\Leave\Actions\UpdateLeaveBalances;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Leave\Models\LeaveBlackout;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Support\LeaveBalances;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyImpact;
use App\Domain\Requests\Actions\ResubmitRequest;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Actions\WithdrawRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
| Phase 5a: the leave ledger (D63–D65). Calendar-year balances, pending requests hold their
| days, approval debits them, overdraw is blocked. Riyadh weekend is Friday + Saturday.
*/
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-01 08:00:00');
    Mail::fake();
    Storage::fake('local');
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create();
    $person = function (string $role, ?Employee $manager = null, ?Department $department = null, string $hired = '2024-01-01'): array {
        $user = userWithRoles($role);

        return [$user, Employee::factory()->placedIn($this->branch, $department, $manager)->create(['user_id' => $user->id, 'hire_date' => $hired])];
    };

    [$this->directorUser, $director] = $person('employee');
    $this->department = Department::factory()->create(['head_employee_id' => $director->id]);
    [$this->managerUser, $manager] = $person('employee', $director, $this->department);
    [$this->hrUser] = $person('hr_officer', $director, $this->department);
    [$this->requesterUser, $this->requester] = $person('employee', $manager, $this->department);
    $this->annual = LeaveType::query()->where('code', 'ANNUAL')->sole();
    $this->sick = LeaveType::query()->where('code', 'SICK')->sole();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @param  array<string, mixed>  $answers
 */
function askLeave(object $test, string $code, array $answers): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($test->requesterUser), RequestType::query()->where('code', $code)->sole(), $answers);
}

function leaveDecide(EmployeeRequest $request, object $as, Decision $decision = Decision::Approved): void
{
    $task = WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
    resolve(WorkflowEngine::class)->decide($task, $decision, $as, $decision === Decision::Approved ? null : 'Not now');
}

function leaveBalance(object $test, ?LeaveType $type = null, int $year = 2026): float
{
    return resolve(LeaveBalances::class)->of($test->requester, $type ?? $test->annual, $year)->available;
}

function leaveViolation(callable $call): string
{
    try {
        $call();
    } catch (DomainRuleViolation $e) {
        return $e->errorCode;
    }

    return 'none';
}

it('posts accrual, carries last year over and stays idempotent', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-31');
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-31');

    $kinds = LeaveLedgerEntry::query()->where('employee_id', $this->requester->id)->where('leave_type_id', $this->annual->id)
        ->orderBy('id')->get()->map(fn (LeaveLedgerEntry $e): string => "{$e->year}:{$e->kind->value}:{$e->amount}")->all();

    // 2025: 21 earned; 5 carried over (expires 31 March); 2026: three months of 21/12 rounded down to the half day.
    expect($kinds)->toBe(['2025:accrual:21', '2026:carry_over:5', '2026:accrual:5'])
        ->and(leaveBalance($this))->toBe(10.0)
        ->and(leaveBalance($this, $this->sick))->toBe(30.0);

    $carry = LeaveLedgerEntry::query()->where('kind', LedgerKind::CarryOver->value)->where('employee_id', $this->requester->id)->sole();
    expect($carry->expires_on?->toDateString())->toBe('2026-03-31');
});

it('expires unused carry-over after its date, net of leave taken before it', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    resolve(AdjustLeaveBalance::class)->handle(Actor::user($this->hrUser), $this->requester, $this->annual, 2026, 2, 'Opening correction');

    $request = askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-09']]);
    leaveDecide($request, $this->managerUser);
    leaveDecide($request, $this->hrUser);

    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-04-01');

    $expiry = LeaveLedgerEntry::query()->where('kind', LedgerKind::CarryExpiry->value)->where('employee_id', $this->requester->id)->sole();
    expect($expiry->amount)->toBe(-3.0)
        ->and($expiry->effective_on->toDateString())->toBe('2026-04-01');
});

it('holds days while pending, debits on approval and releases on rejection', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    $before = leaveBalance($this);

    // Sunday 8 → Thursday 12 March: five working days.
    $request = askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-12']]);
    $leave = LeaveRequest::query()->where('request_id', $request->id)->sole();

    expect($leave->amount)->toBe(5.0)
        ->and($leave->status)->toBe(LeaveRequestStatus::Pending)
        ->and(leaveBalance($this))->toBe($before - 5);

    leaveDecide($request, $this->managerUser);
    leaveDecide($request, $this->hrUser);

    expect($leave->fresh()?->status)->toBe(LeaveRequestStatus::Approved)
        ->and(LeaveLedgerEntry::query()->where('leave_request_id', $leave->id)->sole()->amount)->toBe(-5.0)
        ->and(leaveBalance($this))->toBe($before - 5);

    $second = askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-15', 'to' => '2026-03-15']]);
    leaveDecide($second, $this->managerUser, Decision::Rejected);

    expect(LeaveRequest::query()->where('request_id', $second->id)->sole()->status)->toBe(LeaveRequestStatus::Rejected)
        ->and(leaveBalance($this))->toBe($before - 5);
});

it('blocks overdraw, but counts accrual earned by the leave date', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    // 5 carried over + 3.5 earned in January and February.
    expect(leaveBalance($this))->toBe(8.5);

    expect(leaveViolation(fn () => askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-19']])))->toBe('leave.insufficient')
        ->and(EmployeeRequest::query()->count())->toBe(0);

    // By late October nine months are earned (15.5 after rounding); the carry-over has expired.
    expect(leaveViolation(fn () => askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-10-11', 'to' => '2026-11-05']])))->toBe('leave.insufficient');
    $request = askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-10-25', 'to' => '2026-11-05']]);
    expect(LeaveRequest::query()->where('request_id', $request->id)->sole()->amount)->toBe(10.0);
});

it('rejects overlaps, blackouts, weekends-only and leave across the year end', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-09']]);

    expect(leaveViolation(fn () => askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-09', 'to' => '2026-03-10']])))->toBe('leave.overlap')
        ->and(leaveViolation(fn () => askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-13', 'to' => '2026-03-14']])))->toBe('leave.no_working_days')
        ->and(leaveViolation(fn () => askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-12-30', 'to' => '2027-01-03']])))->toBe('leave.spans_years');

    LeaveBlackout::query()->create(['name' => ['en' => 'Stock count', 'ar' => 'الجرد'], 'starts_on' => '2026-03-22', 'ends_on' => '2026-03-26', 'department_id' => $this->department->id]);
    expect(leaveViolation(fn () => askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-25', 'to' => '2026-03-25']])))->toBe('leave.blackout');

    LeaveBlackout::query()->update(['leave_type_ids' => json_encode([$this->sick->id])]);
    expect(leaveViolation(fn () => askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-25', 'to' => '2026-03-25']])))->toBe('none');
});

it('takes half a day when asked for one date', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    $request = askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-08'], 'half_day' => 'morning']);

    $leave = LeaveRequest::query()->where('request_id', $request->id)->sole();
    expect($leave->amount)->toBe(0.5)->and($leave->half_day)->toBe('morning');
});

it('needs a medical note for sick leave longer than the rule allows', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    $set = PolicySet::query()->sole();
    resolve(SetPolicyValues::class)->handle(Actor::system('test'), $set, ['leave.type.SICK' => ['entitlement' => 30, 'accrual' => 'yearly_upfront', 'proRata' => 'none', 'rounding' => 'none', 'carryOverMax' => 0, 'attachmentAfterDays' => 1]], '2026-01-01', 'Sick note rule');

    askLeave($this, 'SICK_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-08']]);
    expect(leaveViolation(fn () => askLeave($this, 'SICK_LEAVE', ['dates' => ['from' => '2026-03-10', 'to' => '2026-03-11']])))->toBe('leave.attachment_required');

    $form = RequestType::query()->where('code', 'SICK_LEAVE')->sole()->form()->firstOrFail();
    $version = FormVersion::query()->findOrFail($form->published_version_id);
    $upload = resolve(StoreFormUpload::class)->handle($form, $version, 'medical_note', UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'), $this->requesterUser, null);

    askLeave($this, 'SICK_LEAVE', ['dates' => ['from' => '2026-03-10', 'to' => '2026-03-11'], 'medical_note' => [$upload->ulid]]);
    expect(leaveBalance($this, $this->sick))->toBe(27.0);
});

it('enforces the hourly permission quota per request and per month', function (): void {
    $hours = fn (string $date, string $from, string $to): array => ['kind' => 'hourly', 'date' => $date, 'from_time' => $from, 'to_time' => $to, 'reason' => 'Appointment'];

    expect(leaveViolation(fn () => askLeave($this, 'DAY_OFF', $hours('2026-03-08', '09:00', '12:00'))))->toBe('leave.too_long');

    askLeave($this, 'DAY_OFF', $hours('2026-03-08', '09:00', '11:00'));
    askLeave($this, 'DAY_OFF', $hours('2026-03-09', '09:00', '11:00'));

    expect(leaveViolation(fn () => askLeave($this, 'DAY_OFF', $hours('2026-03-10', '09:00', '09:30'))))->toBe('leave.quota')
        ->and(leaveViolation(fn () => askLeave($this, 'DAY_OFF', $hours('2026-04-05', '09:00', '10:00'))))->toBe('none')
        ->and(LeaveRequest::query()->where('kind', 'hourly')->sum('minutes'))->toBe(300);
});

it('moves the hold when a returned request comes back with new dates, and frees it on withdrawal', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    $request = askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-10']]);
    leaveDecide($request, $this->managerUser, Decision::Returned);

    expect($request->fresh()?->status)->toBe(RequestStatus::Returned)
        ->and(leaveBalance($this))->toBe(5.5);

    resolve(ResubmitRequest::class)->handle(Actor::user($this->requesterUser), $request->fresh() ?? $request, ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-08']]);
    expect(LeaveRequest::query()->where('request_id', $request->id)->sole()->amount)->toBe(1.0)
        ->and(leaveBalance($this))->toBe(7.5);

    resolve(WithdrawRequest::class)->handle(Actor::user($this->requesterUser), $request->fresh() ?? $request);
    expect(LeaveRequest::query()->where('request_id', $request->id)->sole()->status)->toBe(LeaveRequestStatus::Cancelled)
        ->and(leaveBalance($this))->toBe(8.5);
});

it('gives days back when HR cancels approved leave', function (): void {
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2026-03-01');
    $request = askLeave($this, 'ANNUAL_LEAVE', ['dates' => ['from' => '2026-03-08', 'to' => '2026-03-09']]);
    leaveDecide($request, $this->managerUser);
    leaveDecide($request, $this->hrUser);
    $leave = LeaveRequest::query()->where('request_id', $request->id)->sole();

    resolve(CancelApprovedLeave::class)->handle(Actor::user($this->hrUser), $leave, 'Trip called off');

    expect($leave->fresh()?->status)->toBe(LeaveRequestStatus::Cancelled)
        ->and(LeaveLedgerEntry::query()->where('leave_request_id', $leave->id)->pluck('amount')->all())->toBe([-2.0, 2.0])
        ->and(leaveBalance($this))->toBe(8.5);
});

it('caps the balance at the maximum and recalculates after a rule change', function (): void {
    $set = PolicySet::query()->sole();
    resolve(SetPolicyValues::class)->handle(Actor::system('test'), $set, ['leave.type.ANNUAL' => ['entitlement' => 21, 'accrual' => 'monthly', 'proRata' => 'daily', 'rounding' => 'half', 'carryOverMax' => 0, 'maxBalance' => 15]], '2025-01-01', 'Cap test');
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2025-12-31');

    expect(leaveBalance($this, null, 2025))->toBe(15.0)
        ->and((float) LeaveLedgerEntry::query()->where('kind', LedgerKind::Cap->value)->sum('amount'))->toBe(-6.0);

    resolve(SetPolicyValues::class)->handle(Actor::system('test'), $set, ['leave.type.ANNUAL' => ['entitlement' => 12, 'accrual' => 'monthly', 'proRata' => 'daily', 'rounding' => 'half', 'carryOverMax' => 0]], '2025-01-01', 'Lower accrual');
    resolve(UpdateLeaveBalances::class)->handle($this->requester, '2025-12-31');

    // Accrual is now 12 and the cap no longer bites, so it is released.
    expect(leaveBalance($this, null, 2025))->toBe(12.0);
});

it('shows leave earned before and after in the change-impact preview', function (): void {
    $set = PolicySet::query()->sole();
    $preview = resolve(PolicyImpact::class)->ofValues($set, ['leave.type.ANNUAL' => ['entitlement' => 30, 'accrual' => 'monthly', 'proRata' => 'daily', 'rounding' => 'half', 'carryOverMax' => 5, 'carryOverExpiresMonths' => 3, 'eligibleAfterDays' => 0, 'tenureSteps' => []]], '2026-01-01');
    $row = collect($preview->rows)->firstWhere('employeeId', $this->requester->ulid);

    expect($row?->balances)->toHaveCount(1)
        ->and($row?->balances[0]->before)->toBe(21.0)
        ->and($row?->balances[0]->after)->toBe(30.0)
        ->and($row?->balances[0]->delta)->toBe(9.0);
});
