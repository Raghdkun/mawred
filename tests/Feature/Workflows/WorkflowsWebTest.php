<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\StepContext;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\Delegation;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Domain\Workflows\Models\WorkflowVersion;
use App\Support\Actors\Actor;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    $this->admin = userWithRoles('hr_admin');
    $this->admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->employee = userWithRoles('employee');
    $this->trip = WorkflowDefinition::query()->where('code', 'BUSINESS_TRIP')->sole();
});

it('lists workflows and opens the builder with the linked form fields', function (): void {
    $this->actingAs($this->admin)->get(route('workflows.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows/index')->has('workflows', 15)->where('workflows.0.publishedVersion', 1));

    $this->actingAs($this->admin)->get(route('workflows.builder', $this->trip))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows/builder')
            ->where('isDraft', false)->where('graph.nodes.1.id', 'manager')
            ->where('catalog.fields.0.key', 'destination')->has('catalog.recordUpdates')->where('can.update', true));

    $this->actingAs($this->employee)->get(route('workflows.index'))->assertForbidden();
});

it('saves a draft, reports problems, and publishes a new version', function (): void {
    $graph = WorkflowVersion::query()->findOrFail($this->trip->published_version_id)->graph;
    $broken = [...$graph, 'nodes' => [...$graph['nodes'], ['id' => 'notify_1', 'type' => 'notify', 'config' => []]]];

    $this->actingAs($this->admin)->put(route('workflows.draft', $this->trip), ['graph' => $broken])->assertRedirect();
    $this->actingAs($this->admin)->get(route('workflows.builder', $this->trip))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('isDraft', true)->has('problems.notify_1'));
    $this->actingAs($this->admin)->post(route('workflows.publish', $this->trip))->assertSessionHasErrors();

    $this->actingAs($this->admin)->put(route('workflows.draft', $this->trip), ['graph' => $graph])->assertRedirect();
    $this->actingAs($this->admin)->post(route('workflows.publish', $this->trip))->assertSessionHasNoErrors();

    expect($this->trip->fresh()?->publishedVersion?->number)->toBe(2);
});

it('discards a draft back to the live version', function (): void {
    $graph = WorkflowVersion::query()->findOrFail($this->trip->published_version_id)->graph;
    $this->actingAs($this->admin)->put(route('workflows.draft', $this->trip), ['graph' => $graph]);
    $this->actingAs($this->admin)->delete(route('workflows.discard', $this->trip))->assertRedirect();

    expect($this->trip->draft()->exists())->toBeFalse();
});

it('test-runs the canvas without saving anything', function (): void {
    $graph = WorkflowVersion::query()->findOrFail($this->trip->published_version_id)->graph;
    $instances = WorkflowInstance::query()->count();

    $this->actingAs($this->admin)->postJson(route('workflows.simulate', $this->trip), ['graph' => $graph, 'fields' => ['destination' => 'Jeddah'], 'decisions' => ['hr' => 'rejected']])
        ->assertOk()->assertJsonPath('outcome', 'rejected')->assertJsonPath('path.1.node', 'manager');

    expect(WorkflowInstance::query()->count())->toBe($instances);
});

it('retries, skips or cancels a workflow stuck on a failed step', function (): void {
    resolve(RecordUpdates::class)->register(new class implements RecordUpdate
    {
        public function key(): string
        {
            return 'test.broken';
        }

        public function label(): array
        {
            return ['en' => 'Broken', 'ar' => 'Broken'];
        }

        public function availableInPhase(): int
        {
            return 0;
        }

        public function apply(StepContext $context): void
        {
            throw new RuntimeException('Payroll is offline');
        }
    });
    $flow = WorkflowDefinition::query()->create(['code' => 'BROKEN', 'name' => ['en' => 'Broken']]);
    $version = WorkflowVersion::query()->create(['workflow_definition_id' => $flow->id, 'number' => 1, 'status' => 'published', 'graph' => [
        'format' => 'mawred.workflow/v1',
        'nodes' => [['id' => 'start', 'type' => 'start', 'config' => []], ['id' => 'apply', 'type' => 'update_record', 'config' => ['action' => 'test.broken']], ['id' => 'end', 'type' => 'end', 'config' => ['outcome' => 'completed']]],
        'edges' => [['id' => 'a', 'source' => 'start', 'target' => 'apply'], ['id' => 'b', 'source' => 'apply', 'target' => 'end']],
    ]]);
    $flow->forceFill(['published_version_id' => $version->id])->save();
    $start = fn (): WorkflowInstance => resolve(WorkflowEngine::class)->start($flow, ['field' => []], null, null);
    $stick = function (WorkflowInstance $instance): WorkflowStep {
        foreach ([10, 60, 300, 900] as $seconds) {
            $this->travel($seconds + 1)->seconds();
            resolve(WorkflowEngine::class)->tick();
        }

        expect($instance->refresh()->status)->toBe(InstanceStatus::Attention);

        return $instance->steps()->where('node_id', 'apply')->sole();
    };

    $skipped = $start();
    $this->actingAs($this->admin)->get(route('workflows.stuck.index'))->assertOk();
    $step = $stick($skipped);
    $this->actingAs($this->admin)->get(route('workflows.stuck.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows/stuck')->where('instances.data.0.step.error', 'Payroll is offline'));
    $this->actingAs($this->admin)->post(route('workflows.stuck.skip', $step))->assertRedirect();
    expect($skipped->refresh()->status)->toBe(InstanceStatus::Completed);

    $cancelled = $start();
    $stick($cancelled);
    $this->actingAs($this->admin)->post(route('workflows.stuck.cancel', $cancelled), ['reason' => 'Payroll retired'])->assertRedirect();
    expect($cancelled->refresh()->status)->toBe(InstanceStatus::Cancelled);

    $this->actingAs($this->employee)->get(route('workflows.stuck.index'))->assertForbidden();
});

it('routes new approvals to a delegate while the approver is away', function (): void {
    $branch = Branch::factory()->riyadh()->create();
    $managerUser = userWithRoles('employee');
    $manager = Employee::factory()->placedIn($branch)->create(['user_id' => $managerUser->id]);
    $peer = userWithRoles('employee');
    Employee::factory()->placedIn($branch)->create(['user_id' => $peer->id]);
    $requester = userWithRoles('employee');
    Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $requester->id]);

    $this->actingAs($managerUser)->post(route('delegations.store'), ['to' => $managerUser->ulid, 'startsOn' => now()->toDateString(), 'endsOn' => now()->toDateString()])->assertSessionHasErrors('to');
    $this->actingAs($managerUser)->post(route('delegations.store'), ['to' => $peer->ulid, 'startsOn' => now()->subDay()->toDateString(), 'endsOn' => now()->addWeek()->toDateString()])->assertRedirect();
    $this->actingAs($managerUser)->get(route('delegations.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('delegations', 1)->where('delegations.0.active', true));

    $request = resolve(SubmitRequest::class)->handle(Actor::user($requester), RequestType::query()->where('code', 'ASSET_REQUEST')->sole(), ['item' => 'laptop', 'details' => 'New']);
    $task = WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
    expect($task->assignee_user_id)->toBe($peer->id)->and($task->original_assignee_user_id)->toBe($managerUser->id);

    $this->actingAs($peer)->delete(route('delegations.destroy', Delegation::query()->sole()))->assertNotFound();
    $this->actingAs($managerUser)->delete(route('delegations.destroy', Delegation::query()->sole()))->assertRedirect();
    expect(Delegation::query()->count())->toBe(0);
});
