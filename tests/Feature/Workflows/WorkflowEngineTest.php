<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Actions\DelegateTask;
use App\Domain\Workflows\Actions\PublishWorkflow;
use App\Domain\Workflows\Actions\SaveWorkflowDefinition;
use App\Domain\Workflows\Actions\SaveWorkflowDraft;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\Simulator;
use App\Domain\Workflows\Engine\StepContext;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\Outcome;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\Delegation;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

beforeEach(function (): void {
    $this->travelTo('2026-10-15 09:00:00');
    seedAccess();
    $this->branch = Branch::factory()->riyadh()->create();
    $this->ops = Department::factory()->create();

    $person = function (string $name, ?Employee $manager = null, string ...$roles): array {
        $user = userWithRoles(...($roles === [] ? ['employee'] : $roles));
        $employee = Employee::factory()->placedIn($this->branch, $this->ops, $manager)->create(['user_id' => $user->id, 'name_en' => $name]);

        return [$user, $employee];
    };

    [$this->bossUser, $this->boss] = $person('Boss');
    [$this->managerUser, $this->manager] = $person('Manager', $this->boss);
    [$this->requesterUser, $this->requester] = $person('Requester', $this->manager);
    [$this->hrUser] = $person('HR Officer', null, 'hr_officer');
    [$this->adminUser] = $person('HR Admin', null, 'hr_admin');
    $this->ops->forceFill(['head_employee_id' => $this->boss->id])->save();
});

/**
 * Builds and publishes a workflow from nodes and edges (edge: [source, target, handle?]).
 *
 * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $nodes  [id, type, config]
 * @param  list<array{0: string, 1: string, 2?: string}>  $edges
 */
function workflow(array $nodes, array $edges, string $code = 'flow'): WorkflowDefinition
{
    $actor = Actor::system('test');
    $definition = resolve(SaveWorkflowDefinition::class)->handle($actor, $code, ['en' => ucfirst($code)]);
    resolve(SaveWorkflowDraft::class)->handle($actor, $definition, [
        'format' => 'mawred.workflow/v1',
        'nodes' => array_map(fn (array $n): array => ['id' => $n[0], 'type' => $n[1], 'config' => $n[2] ?? []], $nodes),
        'edges' => array_map(fn (array $e, int $i): array => ['id' => "e{$i}", 'source' => $e[0], 'target' => $e[1], 'sourceHandle' => $e[2] ?? null], $edges, array_keys($edges)),
    ]);
    resolve(PublishWorkflow::class)->handle($actor, $definition);

    return $definition->fresh() ?? $definition;
}

function managerThenHr(): WorkflowDefinition
{
    return workflow(
        [
            ['start', 'start'],
            ['manager', 'approval', ['assignee' => ['type' => 'manager'], 'sla' => ['hours' => 48, 'reminderHours' => 24]]],
            ['hr', 'approval', ['assignee' => ['type' => 'role', 'role' => 'hr_officer', 'scoped' => true]]],
            ['approved', 'end', ['outcome' => 'approved']],
            ['rejected', 'end', ['outcome' => 'rejected']],
        ],
        [['start', 'manager'], ['manager', 'hr', 'approved'], ['manager', 'rejected', 'rejected'], ['hr', 'approved', 'approved'], ['hr', 'rejected', 'rejected']],
    );
}

function run(object $test, WorkflowDefinition $definition, array $fields = []): WorkflowInstance
{
    return resolve(WorkflowEngine::class)->start($definition, ['field' => $fields], $test->requesterUser, $test->requester);
}

function openTask(WorkflowInstance $instance): WorkflowTask
{
    return WorkflowTask::query()->where('workflow_instance_id', $instance->id)->where('status', TaskStatus::Open->value)->sole();
}

function decide(WorkflowTask $task, Decision $decision, ?string $comment = null): void
{
    resolve(WorkflowEngine::class)->decide($task, $decision, $task->assignee()->firstOrFail(), $comment);
}

it('runs manager then HR to approval', function (): void {
    $instance = run($this, managerThenHr());
    expect(openTask($instance)->assignee_user_id)->toBe($this->managerUser->id);

    decide(openTask($instance), Decision::Approved);
    expect(openTask($instance)->assignee_user_id)->toBe($this->hrUser->id);

    decide(openTask($instance), Decision::Approved);
    $instance->refresh();

    expect($instance)->status->toBe(InstanceStatus::Completed)->outcome->toBe(Outcome::Approved)
        ->and($instance->timeline()->pluck('event')->all())->toBe(['started', 'task.assigned', 'task.approved', 'task.assigned', 'task.approved', 'completed']);
});

it('rejects with a reason, and refuses a rejection without one', function (): void {
    $instance = run($this, managerThenHr());

    expect(fn () => decide(openTask($instance), Decision::Rejected))->toThrow(DomainRuleViolation::class);

    decide(openTask($instance), Decision::Rejected, 'Busy season');
    expect($instance->refresh())->status->toBe(InstanceStatus::Completed)->outcome->toBe(Outcome::Rejected);
});

it('returns for changes and re-enters the same approval on resubmission', function (): void {
    $instance = run($this, managerThenHr(), ['days' => 3]);
    decide(openTask($instance), Decision::Returned, 'Add the dates');
    expect($instance->refresh()->status)->toBe(InstanceStatus::Returned);

    resolve(WorkflowEngine::class)->resubmit($instance, ['days' => 2], $this->requesterUser);
    $task = openTask($instance);

    expect($task->assignee_user_id)->toBe($this->managerUser->id)
        ->and($task->ownerStep()->iteration)->toBe(2)
        ->and($instance->refresh()->context['field'])->toBe(['days' => 2]);
});

it('routes to a delegate, and lets an assignee delegate by hand', function (): void {
    Delegation::query()->create(['from_user_id' => $this->managerUser->id, 'to_user_id' => $this->bossUser->id, 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-20']);
    $instance = run($this, managerThenHr());
    $task = openTask($instance);

    expect($task)->assignee_user_id->toBe($this->bossUser->id)->original_assignee_user_id->toBe($this->managerUser->id);

    resolve(DelegateTask::class)->handle($task, $this->bossUser, $this->adminUser);
    expect($task->fresh())->assignee_user_id->toBe($this->adminUser->id)->delegated_from_user_id->toBe($this->bossUser->id);
});

it('reminds after 24 hours and escalates to the manager\'s manager after 48', function (): void {
    $instance = run($this, managerThenHr());

    $this->travel(25)->hours();
    resolve(WorkflowEngine::class)->tick();
    expect(openTask($instance)->reminded_at)->not->toBeNull();

    $this->travel(24)->hours();
    resolve(WorkflowEngine::class)->tick();
    $task = openTask($instance);

    expect($task)->assignee_user_id->toBe($this->bossUser->id)->original_assignee_user_id->toBe($this->managerUser->id)->escalated_at->not->toBeNull()
        ->and($instance->timeline()->pluck('event')->all())->toContain('task.reminded', 'task.escalated');
});

it('runs parallel branches and joins them', function (): void {
    $definition = workflow(
        [
            ['start', 'start'], ['split', 'split'],
            ['manager', 'approval', ['assignee' => ['type' => 'manager']]],
            ['head', 'approval', ['assignee' => ['type' => 'department_head']]],
            ['join', 'join'], ['approved', 'end', ['outcome' => 'approved']], ['rejected', 'end', ['outcome' => 'rejected']],
        ],
        [['start', 'split'], ['split', 'manager'], ['split', 'head'], ['manager', 'join', 'approved'], ['head', 'join', 'approved'], ['join', 'approved'], ['manager', 'rejected', 'rejected'], ['head', 'rejected', 'rejected']],
    );
    $instance = run($this, $definition);
    $tasks = WorkflowTask::query()->where('workflow_instance_id', $instance->id)->get();

    expect($tasks->pluck('assignee_user_id')->sort()->values()->all())->toBe(collect([$this->managerUser->id, $this->bossUser->id])->sort()->values()->all());

    decide($tasks->firstWhere('assignee_user_id', $this->managerUser->id), Decision::Approved);
    expect($instance->refresh()->status)->toBe(InstanceStatus::Running);

    decide($tasks->firstWhere('assignee_user_id', $this->bossUser->id), Decision::Approved);
    expect($instance->refresh())->status->toBe(InstanceStatus::Completed)->outcome->toBe(Outcome::Approved);
});

it('branches on conditions over the form', function (): void {
    $definition = workflow(
        [
            ['start', 'start'], ['big', 'condition', ['rule' => ['op' => 'gt', 'args' => [['ref' => 'field.amount'], 1000]]]],
            ['manager', 'approval', ['assignee' => ['type' => 'manager']]], ['done', 'end', ['outcome' => 'approved']], ['no', 'end', ['outcome' => 'rejected']],
        ],
        [['start', 'big'], ['big', 'manager', 'true'], ['big', 'done', 'false'], ['manager', 'done', 'approved'], ['manager', 'no', 'rejected']],
    );

    expect(run($this, $definition, ['amount' => 200])->refresh()->outcome)->toBe(Outcome::Approved)
        ->and(openTask(run($this, $definition, ['amount' => 5000]))->assignee_user_id)->toBe($this->managerUser->id);
});

it('waits on a delay until the tick finds it due', function (): void {
    $instance = run($this, workflow([['start', 'start'], ['wait', 'delay', ['hours' => 2]], ['end', 'end']], [['start', 'wait'], ['wait', 'end']]));

    resolve(WorkflowEngine::class)->tick();
    expect($instance->refresh()->status)->toBe(InstanceStatus::Running);

    $this->travel(2)->hours();
    resolve(WorkflowEngine::class)->tick();
    expect($instance->refresh()->status)->toBe(InstanceStatus::Completed);
});

it('retries a failing step with backoff, then needs attention, and can be retried', function (): void {
    $calls = 0;
    resolve(RecordUpdates::class)->register(new class($calls) implements RecordUpdate
    {
        public function __construct(private int &$calls) {}

        public function key(): string
        {
            return 'test.flaky';
        }

        public function label(): array
        {
            return ['en' => 'Flaky', 'ar' => 'Flaky'];
        }

        public function availableInPhase(): int
        {
            return 0;
        }

        public function apply(StepContext $context): void
        {
            if (++$this->calls < 6) {
                throw new RuntimeException('Downstream is down');
            }
        }
    });
    $instance = run($this, workflow([['start', 'start'], ['update', 'update_record', ['action' => 'test.flaky']], ['end', 'end']], [['start', 'update'], ['update', 'end']]));

    foreach ([10, 60, 300, 900] as $seconds) {
        $this->travel($seconds + 1)->seconds();
        resolve(WorkflowEngine::class)->tick();
    }

    $instance->refresh();
    expect($instance)->status->toBe(InstanceStatus::Attention)->attention_reason->toBe('Downstream is down');

    resolve(WorkflowEngine::class)->retry($instance->steps()->where('node_id', 'update')->sole(), $this->adminUser);
    expect($instance->refresh()->status)->toBe(InstanceStatus::Completed);
});

it('runs a sub-workflow and continues on its outcome', function (): void {
    workflow([['start', 'start'], ['head', 'approval', ['assignee' => ['type' => 'department_head']]], ['yes', 'end', ['outcome' => 'approved']], ['no', 'end', ['outcome' => 'rejected']]],
        [['start', 'head'], ['head', 'yes', 'approved'], ['head', 'no', 'rejected']], 'child');
    $parent = workflow([['start', 'start'], ['sub', 'subworkflow', ['workflow' => 'CHILD']], ['ok', 'end', ['outcome' => 'approved']], ['ko', 'end', ['outcome' => 'rejected']]],
        [['start', 'sub'], ['sub', 'ok', 'approved'], ['sub', 'ko', 'rejected']], 'parent');

    $instance = run($this, $parent);
    $child = WorkflowInstance::query()->whereNotNull('parent_step_id')->sole();
    decide(openTask($child), Decision::Approved);

    expect($child->refresh()->outcome)->toBe(Outcome::Approved)
        ->and($instance->refresh())->status->toBe(InstanceStatus::Completed)->outcome->toBe(Outcome::Approved);
});

it('falls back to the fallback role when nobody resolves, never to the requester', function (): void {
    $this->requester->forceFill(['current_manager_id' => null])->save();
    $instance = run($this, managerThenHr());

    expect(openTask($instance)->assignee_user_id)->toBe($this->adminUser->id)
        ->and($instance->timeline()->pluck('event')->all())->toContain('assignee.fallback');
});

it('cancels an open instance and its tasks', function (): void {
    $instance = run($this, managerThenHr());
    resolve(WorkflowEngine::class)->cancel($instance, $this->requesterUser, 'Plans changed');

    expect($instance->refresh())->status->toBe(InstanceStatus::Cancelled)->outcome->toBe(Outcome::Cancelled)
        ->and(WorkflowTask::query()->sole()->status)->toBe(TaskStatus::Cancelled);
});

it('refuses to publish broken graphs', function (): void {
    $actor = Actor::system('test');
    $definition = resolve(SaveWorkflowDefinition::class)->handle($actor, 'broken', ['en' => 'Broken']);
    resolve(SaveWorkflowDraft::class)->handle($actor, $definition, [
        'format' => 'mawred.workflow/v1',
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'config' => []],
            ['id' => 'a', 'type' => 'approval', 'config' => []],
            ['id' => 'orphan', 'type' => 'notify', 'config' => []],
            ['id' => 'end', 'type' => 'end', 'config' => []],
        ],
        'edges' => [['id' => 'e1', 'source' => 'start', 'target' => 'a'], ['id' => 'e2', 'source' => 'a', 'target' => 'end', 'sourceHandle' => 'approved']],
    ]);

    expect(fn () => resolve(PublishWorkflow::class)->handle($actor, $definition))
        ->toThrow(fn (DomainRuleViolation $e) => expect($e->details)->toHaveKeys(['graph.a', 'graph.orphan']));
});

it('simulates a path without saving anything', function (): void {
    $graph = managerThenHr()->publishedVersion()->firstOrFail()->graph;
    $before = [WorkflowInstance::query()->count(), WorkflowTask::query()->count()];

    $result = resolve(Simulator::class)->run($graph, [], ['manager' => 'approved', 'hr' => 'rejected'], $this->requester);

    expect($result['problems'])->toBe([])
        ->and($result['outcome'])->toBe('rejected')
        ->and(array_column($result['path'], 'node'))->toBe(['start', 'manager', 'hr', 'rejected'])
        ->and($result['path'][1]['assignees'])->toBe(['Manager'])
        ->and([WorkflowInstance::query()->count(), WorkflowTask::query()->count()])->toBe($before);
});
