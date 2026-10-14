<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Actions\IssueActionLinks;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();

    $this->riyadh = Branch::factory()->riyadh()->create();
    $this->dubai = Branch::factory()->create();
    $place = function (string $role, Branch $branch, ?Employee $manager = null): array {
        $user = userWithRoles($role);
        // HR roles must have two-factor on before they reach the app.
        $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

        return [$user, Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $user->id])];
    };

    [$this->managerUser, $manager] = $place('employee', $this->riyadh);
    [$this->requesterUser] = $place('employee', $this->riyadh, $manager);
    [$this->colleagueUser] = $place('employee', $this->riyadh);
    [$this->hrUser] = $place('hr_officer', $this->riyadh);
    [$this->otherHrUser] = $place('hr_officer', $this->dubai);
});

function httpSend(object $test, string $code = 'ASSET_REQUEST', array $answers = ['item' => 'laptop', 'details' => 'New hire']): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($test->requesterUser), RequestType::query()->where('code', $code)->sole(), $answers);
}

function httpTask(EmployeeRequest $request): WorkflowTask
{
    return WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
}

it('submits from the request form and lands on the request with a success state', function (): void {
    $type = RequestType::query()->where('code', 'ASSET_REQUEST')->sole();

    $this->actingAs($this->requesterUser)->get(route('requests.create', $type))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('requests/create')->where('type.code', 'ASSET_REQUEST')->has('schema.pages'));

    $response = $this->actingAs($this->requesterUser)->post(route('requests.store', $type), ['values' => ['item' => 'laptop', 'details' => 'New hire']]);
    $request = EmployeeRequest::query()->sole();

    $response->assertRedirect(route('requests.show', $request));
    $this->actingAs($this->requesterUser)->get(route('requests.show', $request))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('requests/show')->where('request.status', 'in_review')->has('timeline')->where('can.withdraw', true));
});

it('maps invalid answers back onto the form', function (): void {
    $type = RequestType::query()->where('code', 'ASSET_REQUEST')->sole();

    $this->actingAs($this->requesterUser)->post(route('requests.store', $type), ['values' => ['item' => 'spaceship']])
        ->assertSessionHasErrors(['item', 'details']);
});

it('hides a request from people outside its scope', function (): void {
    $request = httpSend($this);

    $this->actingAs($this->colleagueUser)->get(route('requests.show', $request))->assertNotFound();
    $this->actingAs($this->otherHrUser)->get(route('requests.show', $request))->assertNotFound();
    $this->actingAs($this->hrUser)->get(route('requests.show', $request))->assertOk();
    $this->actingAs($this->managerUser)->get(route('requests.show', $request))->assertOk();
});

it('lists HR-visible requests by branch only', function (): void {
    httpSend($this);

    $this->actingAs($this->hrUser)->get(route('requests.index', ['tab' => 'all']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.tab', 'all')->has('requests.data', 1));
    $this->actingAs($this->otherHrUser)->get(route('requests.index', ['tab' => 'all']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('requests.data', 0));
});

it('decides from the approvals inbox, and only as the assignee', function (): void {
    $request = httpSend($this);
    $task = httpTask($request);

    $this->actingAs($this->managerUser)->get(route('approvals.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('approvals/index')->has('tasks', 1)->where('selected.id', $task->ulid)->where('detail.request.id', $request->ulid));

    $this->actingAs($this->colleagueUser)->post(route('approvals.decide', $task), ['decision' => 'approved'])->assertNotFound();
    $this->actingAs($this->managerUser)->post(route('approvals.decide', $task), ['decision' => 'rejected'])->assertSessionHasErrors('comment');
    $this->actingAs($this->managerUser)->post(route('approvals.decide', $task), ['decision' => 'approved'])->assertRedirect(route('approvals.index'));

    expect(httpTask($request)->assignee_user_id)->toBe($this->hrUser->id);
});

it('bulk approves only the caller’s own approvals', function (): void {
    $mine = [httpTask(httpSend($this)), httpTask(httpSend($this))];

    $this->actingAs($this->managerUser)->post(route('approvals.bulk'), ['tasks' => [...array_map(fn (WorkflowTask $task): string => $task->ulid, $mine)]])
        ->assertRedirect(route('approvals.index'));

    expect(WorkflowTask::query()->where('assignee_user_id', $this->managerUser->id)->where('status', TaskStatus::Open->value)->count())->toBe(0)
        ->and(WorkflowTask::query()->where('assignee_user_id', $this->hrUser->id)->where('status', TaskStatus::Open->value)->count())->toBe(2);
});

it('delegates through the inbox to someone picked from the delegate search', function (): void {
    $task = httpTask(httpSend($this));

    $this->actingAs($this->managerUser)->getJson('/api/v1/delegates?search='.rawurlencode(Employee::query()->where('user_id', $this->colleagueUser->id)->sole()->name_en))
        ->assertOk()->assertJsonPath('data.0.id', $this->colleagueUser->ulid)->assertJsonMissingPath('data.0.email');

    $this->actingAs($this->managerUser)->post(route('approvals.delegate', $task), ['to' => $this->colleagueUser->ulid])->assertRedirect();

    expect($task->fresh()?->assignee_user_id)->toBe($this->colleagueUser->id);
});

it('decides through a one-time email link after confirming', function (): void {
    $task = httpTask($request = httpSend($this));
    $links = resolve(IssueActionLinks::class)->handle($task);
    $token = basename(parse_url($links['approved'], PHP_URL_PATH) ?: '');

    $this->get($links['approved'])->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('public/action-link')->where('state', 'ready')->where('decision', 'approved'));
    expect($task->fresh()?->status)->toBe(TaskStatus::Open);

    $this->post(route('action-links.confirm', $token))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'done'));
    $this->get($links['rejected'])->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'expired'));

    expect(httpTask($request)->assignee_user_id)->toBe($this->hrUser->id);
});

it('requires the assignee to sign in for links on sensitive requests', function (): void {
    $task = httpTask(httpSend($this, 'SALARY_CERTIFICATE', ['addressed_to' => 'Bank', 'language' => 'ar']));
    $links = resolve(IssueActionLinks::class)->handle($task);

    $this->get($links['approved'])->assertRedirect(route('login'));
    $this->actingAs($this->colleagueUser)->get($links['approved'])->assertRedirect(route('login'));
    $this->actingAs($this->hrUser)->get($links['approved'])->assertOk();
});

it('lets the requester withdraw over HTTP, but nobody else', function (): void {
    $request = httpSend($this);

    $this->actingAs($this->managerUser)->post(route('requests.withdraw', $request))->assertForbidden();
    $this->actingAs($this->requesterUser)->post(route('requests.withdraw', $request))->assertRedirect();

    expect($request->fresh()?->status)->toBe(RequestStatus::Cancelled);
});
