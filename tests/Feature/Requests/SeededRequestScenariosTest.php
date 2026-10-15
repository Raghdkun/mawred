<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\ResubmitRequest;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Actions\WithdrawRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Actions\DelegateTask;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
| Phase 3b exit: each seeded request type runs its default chain (D56), and the common paths
| (reject, return, escalate, delegate, cancel) behave on a seeded workflow.
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create();
    $person = function (string $role, ?Employee $manager = null, ?Department $department = null): array {
        $user = userWithRoles($role);

        return [$user, Employee::factory()->placedIn($this->branch, $department, $manager)->create(['user_id' => $user->id])];
    };

    [$this->directorUser, $director] = $person('employee');
    $this->department = Department::factory()->create(['head_employee_id' => $director->id]);
    [$this->managerUser, $manager] = $person('employee', $director, $this->department);
    [$this->peerUser] = $person('employee', $director, $this->department);
    [$this->hrUser] = $person('hr_officer', $director, $this->department);
    [$this->requesterUser, $this->requester] = $person('employee', $manager, $this->department);
});

function seeded(string $code): RequestType
{
    return RequestType::query()->where('code', $code)->sole();
}

/**
 * @param  array<string, mixed>  $answers
 */
function sendSeeded(object $test, string $code, array $answers): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($test->requesterUser), seeded($code), $answers);
}

function openTaskOf(EmployeeRequest $request): WorkflowTask
{
    return WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
}

function decideStep(EmployeeRequest $request, User $as, Decision $decision = Decision::Approved, ?string $comment = null): void
{
    resolve(WorkflowEngine::class)->decide(openTaskOf($request), $decision, $as, $comment);
}

function receipt(object $test): string
{
    $form = seeded('EXPENSE')->form()->firstOrFail();
    $version = FormVersion::query()->findOrFail($form->published_version_id);

    return resolve(StoreFormUpload::class)->handle($form, $version, 'receipts', UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'), $test->requesterUser, null)->ulid;
}

dataset('seeded types', [
    'day off' => ['DAY_OFF', fn () => ['kind' => 'hourly', 'date' => CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->toDateString(), 'from_time' => '09:00', 'to_time' => '11:00'], ['managerUser', 'hrUser']],
    'business trip' => ['BUSINESS_TRIP', fn () => ['destination' => 'Jeddah', 'dates' => ['from' => CarbonImmutable::now()->addWeek()->toDateString(), 'to' => CarbonImmutable::now()->addWeek()->addDays(2)->toDateString()], 'purpose' => 'Client visit'], ['managerUser', 'hrUser']],
    'expense' => ['EXPENSE', fn (object $test) => ['description' => 'Taxi', 'spent_on' => CarbonImmutable::now()->subDay()->toDateString(), 'amount' => ['amount_minor' => 4500, 'currency' => 'SAR'], 'receipts' => [receipt($test)]], ['managerUser', 'hrUser']],
    'salary certificate' => ['SALARY_CERTIFICATE', fn () => ['addressed_to' => 'Al Rajhi Bank', 'language' => 'ar'], ['hrUser']],
    'experience letter' => ['EXPERIENCE_LETTER', fn () => ['addressed_to' => 'To whom it may concern', 'language' => 'en'], ['hrUser']],
    'salary advance' => ['SALARY_ADVANCE', fn () => ['amount' => ['amount_minor' => 300000, 'currency' => 'SAR'], 'months' => 3, 'reason' => 'Rent'], ['managerUser', 'directorUser', 'hrUser']],
    'resignation' => ['RESIGNATION', fn () => ['last_day' => CarbonImmutable::now()->addMonth()->toDateString()], ['managerUser', 'directorUser', 'hrUser']],
    'details change' => ['DETAILS_CHANGE', fn () => ['change' => ['bank'], 'bank_name' => 'Alinma', 'iban' => 'SA03 8000 0000 6080 1016 7519', 'account_name' => 'Requester'], ['hrUser']],
    'asset request' => ['ASSET_REQUEST', fn () => ['item' => 'laptop', 'details' => 'For the new project'], ['managerUser', 'hrUser']],
]);

it('runs each seeded type through its approval chain', function (string $code, Closure $answers, array $chain): void {
    $request = sendSeeded($this, $code, $answers($this));

    foreach ($chain as $approver) {
        expect(openTaskOf($request)->assignee_user_id)->toBe($this->{$approver}->id);
        decideStep($request, $this->{$approver});
    }

    expect($request->fresh()?->status)->toBe(RequestStatus::Approved);
})->with('seeded types');

it('applies an approved bank details change to the requester record', function (): void {
    $request = sendSeeded($this, 'DETAILS_CHANGE', ['change' => ['bank'], 'bank_name' => 'Alinma', 'iban' => 'sa03 8000 0000 6080 1016 7519', 'account_name' => 'Requester']);
    expect($this->requester->fresh()?->bank_details)->toBeNull();

    decideStep($request, $this->hrUser);

    expect($this->requester->fresh()?->bank_details)->toBe(['bank_name' => 'Alinma', 'iban' => 'SA0380000000608010167519', 'account_name' => 'Requester']);
});

it('keeps flagged types out of the request center until their module ships', function (): void {
    $this->actingAs($this->requesterUser)->get(route('requests.center'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('types', 11));
    $this->actingAs($this->requesterUser)->get(route('requests.create', seeded('SHIFT_SWAP')))->assertNotFound();
});

it('stops at the first rejection', function (): void {
    $request = sendSeeded($this, 'ASSET_REQUEST', ['item' => 'phone', 'details' => 'Old one broke']);
    decideStep($request, $this->managerUser, Decision::Rejected, 'Use the pool phone');

    expect($request->fresh()?->status)->toBe(RequestStatus::Rejected)
        ->and(WorkflowTask::query()->where('assignee_user_id', $this->hrUser->id)->exists())->toBeFalse();
});

it('returns to the requester and resumes at the same step', function (): void {
    $request = sendSeeded($this, 'BUSINESS_TRIP', ['destination' => 'Jeddah', 'dates' => ['from' => CarbonImmutable::now()->addWeek()->toDateString(), 'to' => CarbonImmutable::now()->addWeek()->toDateString()], 'purpose' => 'Visit']);
    decideStep($request, $this->managerUser);
    decideStep($request, $this->hrUser, Decision::Returned, 'Add the hotel');

    resolve(ResubmitRequest::class)->handle(Actor::user($this->requesterUser), $request->fresh(), ['destination' => 'Jeddah', 'dates' => ['from' => CarbonImmutable::now()->addWeek()->toDateString(), 'to' => CarbonImmutable::now()->addWeek()->toDateString()], 'purpose' => 'Visit, Hilton']);

    expect(openTaskOf($request)->assignee_user_id)->toBe($this->hrUser->id)
        ->and($request->fresh()?->status)->toBe(RequestStatus::InReview);
});

it('keeps attached files when a returned request is corrected', function (): void {
    $file = receipt($this);
    $request = sendSeeded($this, 'EXPENSE', ['description' => 'Taxi', 'spent_on' => CarbonImmutable::now()->subDay()->toDateString(), 'amount' => ['amount_minor' => 4500, 'currency' => 'SAR'], 'receipts' => [$file]]);
    decideStep($request, $this->managerUser, Decision::Returned, 'Split the tip');

    resolve(ResubmitRequest::class)->handle(Actor::user($this->requesterUser), $request->fresh(), ['description' => 'Taxi', 'spent_on' => CarbonImmutable::now()->subDay()->toDateString(), 'amount' => ['amount_minor' => 4000, 'currency' => 'SAR'], 'receipts' => [$file]]);

    expect($request->fresh()?->submission()->sole()->data['receipts'][0]['id'])->toBe($file);
});

it('escalates an overdue approval to the approver’s manager', function (): void {
    $request = sendSeeded($this, 'ASSET_REQUEST', ['item' => 'monitor', 'details' => 'Second screen']);

    $this->travel(49)->hours();
    $this->artisan('workflows:tick')->assertSuccessful();

    expect(openTaskOf($request)->assignee_user_id)->toBe($this->directorUser->id);
});

it('lets an approver delegate to a colleague who then decides', function (): void {
    $request = sendSeeded($this, 'ASSET_REQUEST', ['item' => 'laptop', 'details' => 'Replacement']);
    resolve(DelegateTask::class)->handle(openTaskOf($request), $this->managerUser, $this->peerUser, 'On leave');

    expect(openTaskOf($request)->assignee_user_id)->toBe($this->peerUser->id);
    decideStep($request, $this->peerUser);

    expect(openTaskOf($request)->assignee_user_id)->toBe($this->hrUser->id);
});

it('cancels the open approvals when the requester withdraws', function (): void {
    $request = sendSeeded($this, 'EXPERIENCE_LETTER', ['addressed_to' => 'Embassy', 'language' => 'en']);
    resolve(WithdrawRequest::class)->handle(Actor::user($this->requesterUser), $request);

    expect($request->fresh()?->status)->toBe(RequestStatus::Cancelled)
        ->and(WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->exists())->toBeFalse();
});
