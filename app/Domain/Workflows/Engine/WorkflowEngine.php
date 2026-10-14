<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\DecisionChannel;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\Outcome;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Enums\TaskKind;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Events\InstanceFinished;
use App\Domain\Workflows\Events\TaskAssigned;
use App\Domain\Workflows\Jobs\RunStep;
use App\Domain\Workflows\Models\ActionToken;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Domain\Workflows\Models\WorkflowVersion;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The workflow engine (ADR-0009): a token-passing state machine over versioned graphs. Each step
 * runs under a per-instance lock with row locks; follow-up steps are dispatched only after the
 * lock is released. Human tasks and timers wait in the database and are swept by `tick()`.
 */
final class WorkflowEngine
{
    /** Retry backoff in seconds; after the last one the step fails and the instance needs attention. */
    public const array BACKOFF = [10, 60, 300, 900];

    /** Set by the simulator: run inline, record side effects instead of performing them. */
    public bool $simulating = false;

    /** @var list<int> parent sub-workflow steps to wake once the current lock is released */
    private array $wake = [];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly NodeRegistry $nodes,
        private readonly AssigneeResolver $resolver,
    ) {}

    /**
     * Starts an instance on the definition's published version.
     *
     * @param  array<string, mixed>  $context  `field`, `requester`, `ctx`, `request`…
     */
    public function start(WorkflowDefinition $definition, array $context, ?User $requester, ?Employee $employee, ?Model $subject = null, ?WorkflowStep $parent = null, ?WorkflowVersion $version = null): WorkflowInstance
    {
        $version ??= $definition->publishedVersion()->first();

        if (! $version instanceof WorkflowVersion) {
            throw new DomainRuleViolation('workflows.unpublished', __('This workflow has not been published yet.'));
        }

        $graph = Graph::from($version->graph);
        $start = $graph->nodesOfType('start')[0] ?? throw new DomainRuleViolation('workflows.no_start', __('A workflow needs exactly one start.'));

        $instance = $this->db->transaction(function () use ($definition, $version, $context, $requester, $employee, $subject, $parent, $start): WorkflowInstance {
            $instance = new WorkflowInstance([
                'workflow_version_id' => $version->id,
                'workflow_definition_id' => $definition->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'status' => InstanceStatus::Running,
                'context' => [...$context, 'steps' => (object) []],
                'requester_user_id' => $requester?->id,
                'requester_employee_id' => $employee?->id,
                'parent_step_id' => $parent?->id,
                'started_at' => $this->now(),
            ]);
            $instance->save();

            Timeline::add($instance, 'started', null, $requester?->id);
            $this->activate($instance, $start, 1, '');

            return $instance;
        });

        $this->advance($instance);

        return $instance;
    }

    /** Runs (or queues) every step that is due. */
    public function advance(WorkflowInstance $instance): void
    {
        $due = WorkflowStep::query()
            ->where('workflow_instance_id', $instance->id)
            ->where('status', StepStatus::Pending->value)
            ->where(fn (Builder $q) => $q->whereNull('available_at')->orWhere('available_at', '<=', $this->now()))
            ->orderBy('id')
            ->pluck('id');

        foreach ($due as $stepId) {
            if (! is_int($stepId)) {
                continue;
            }

            if ($this->simulating) {
                $this->runStep($stepId);
            } else {
                dispatch(new RunStep($stepId));
            }
        }
    }

    /** The body of a RunStep job: run one pending step's handler and act on the result. */
    public function runStep(int $stepId): void
    {
        $instanceId = WorkflowStep::query()->whereKey($stepId)->value('workflow_instance_id');

        if (! is_int($instanceId)) {
            return;
        }

        $followUp = $this->locked($instanceId, function () use ($stepId): ?WorkflowInstance {
            $step = WorkflowStep::query()->whereKey($stepId)->lockForUpdate()->first();
            $instance = $step instanceof WorkflowStep ? WorkflowInstance::query()->whereKey($step->workflow_instance_id)->lockForUpdate()->first() : null;

            if (! $step instanceof WorkflowStep || ! $instance instanceof WorkflowInstance || $step->status !== StepStatus::Pending) {
                return null;
            }

            if ($instance->status !== InstanceStatus::Running) {
                $step->forceFill(['status' => StepStatus::Cancelled, 'finished_at' => $this->now()])->save();

                return null;
            }

            $graph = Graph::from($instance->pinnedVersion()->graph);
            $node = $graph->node($step->node_id);

            if ($node === null) {
                return $this->fail($instance, $step, __('This step no longer exists in the workflow.'), retryable: false);
            }

            $step->forceFill(['status' => StepStatus::Running, 'attempts' => $step->attempts + 1, 'started_at' => $step->started_at ?? $this->now()])->save();
            $context = new StepContext($instance, $step, $graph, $node['config'], $this->now(), $this->simulating);

            try {
                $result = $this->nodes->get($node['type'])->handle($context);
            } catch (Throwable $e) {
                report($e);
                $result = NodeResult::failed($e->getMessage());
            }

            return $this->apply($instance, $step, $graph, $result);
        });

        if ($followUp instanceof WorkflowInstance) {
            $this->advance($followUp);
        }

        $this->startChildren($instanceId);
        $this->flushWake();
    }

    /**
     * Records a person's decision on a task and moves the instance on.
     */
    public function decide(WorkflowTask $task, Decision $decision, ?User $actor, ?string $comment = null, DecisionChannel $channel = DecisionChannel::Web): void
    {
        $followUp = $this->locked($task->workflow_instance_id, function () use ($task, $decision, $actor, $comment, $channel): ?WorkflowInstance {
            $task = WorkflowTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $instance = WorkflowInstance::query()->whereKey($task->workflow_instance_id)->lockForUpdate()->firstOrFail();
            $step = WorkflowStep::query()->whereKey($task->workflow_step_id)->lockForUpdate()->firstOrFail();

            if ($task->status !== TaskStatus::Open || $instance->status !== InstanceStatus::Running) {
                throw new DomainRuleViolation('workflows.task_closed', __('This has already been decided.'));
            }

            $graph = Graph::from($instance->pinnedVersion()->graph);
            $config = $graph->node($step->node_id)['config'] ?? [];
            $allowed = $task->kind === TaskKind::Task
                ? [Decision::Done]
                : [Decision::Approved, Decision::Rejected, ...(($config['allowReturn'] ?? true) === false ? [] : [Decision::Returned])];

            if (! in_array($decision, $allowed, true)) {
                throw new DomainRuleViolation('workflows.decision_invalid', __('That decision is not available here.'));
            }

            if (in_array($decision, [Decision::Rejected, Decision::Returned], true) && ($comment === null || trim($comment) === '')) {
                throw new DomainRuleViolation('workflows.comment_required', __('Add a comment so the requester knows why.'), ['comment' => [__('Add a comment so the requester knows why.')]]);
            }

            $task->forceFill([
                'status' => TaskStatus::Done,
                'decision' => $decision,
                'comment' => $comment,
                'decided_via' => $channel,
                'decided_by_user_id' => $actor?->id,
                'decided_at' => $this->now(),
            ])->save();
            ActionToken::query()->where('workflow_task_id', $task->id)->whereNull('used_at')->update(['used_at' => $this->now()]);
            Timeline::add($instance, "task.{$decision->value}", $step, $actor?->id, ['task' => $task->ulid, 'comment' => $comment, 'via' => $channel->value]);

            $open = $step->tasks()->where('status', TaskStatus::Open->value)->whereKeyNot($task->id);

            if ($decision === Decision::Returned) {
                $open->update(['status' => TaskStatus::Cancelled->value]);
                $step->forceFill(['status' => StepStatus::Completed, 'output' => ['decision' => 'returned'], 'finished_at' => $this->now()])->save();
                $instance->forceFill([
                    'status' => InstanceStatus::Returned,
                    'context' => [...$instance->context, 'returned' => ['node' => $step->node_id, 'iteration' => $step->iteration, 'branch' => $step->branch_key]],
                ])->save();

                if (! $this->simulating) {
                    event(new InstanceFinished($instance));
                }

                return null;
            }

            $all = ($config['mode'] ?? 'any') === 'all';

            if ($task->kind === TaskKind::Approval && $all && $decision === Decision::Approved && $open->exists()) {
                return null;
            }

            $open->update(['status' => TaskStatus::Cancelled->value]);
            $handle = $decision === Decision::Done ? 'done' : $decision->value;

            return $this->apply($instance, $step, $graph, NodeResult::completed(['decision' => $decision->value, 'by' => $actor?->id], [$handle]));
        });

        if ($followUp instanceof WorkflowInstance) {
            $this->advance($followUp);
        }

        $this->flushWake();
    }

    /**
     * After "return for changes": the requester sends updated answers and the instance re-enters
     * the node that returned it, one iteration later.
     *
     * @param  array<string, mixed>  $fields
     */
    public function resubmit(WorkflowInstance $instance, array $fields, ?User $actor): void
    {
        $followUp = $this->locked($instance->id, function () use ($instance, $fields, $actor): WorkflowInstance {
            $instance = WorkflowInstance::query()->whereKey($instance->id)->lockForUpdate()->firstOrFail();
            $returned = $instance->context['returned'] ?? null;

            if ($instance->status !== InstanceStatus::Returned || ! is_array($returned) || ! is_string($returned['node'] ?? null)) {
                throw new DomainRuleViolation('workflows.not_returned', __('This request is not waiting for changes.'));
            }

            $node = Graph::from($instance->pinnedVersion()->graph)->node($returned['node']) ?? throw new DomainRuleViolation('workflows.not_returned', __('This request is not waiting for changes.'));
            $context = $instance->context;
            unset($context['returned']);
            $instance->forceFill(['status' => InstanceStatus::Running, 'context' => [...$context, 'field' => $fields]])->save();

            Timeline::add($instance, 'resubmitted', null, $actor?->id);
            $this->activate($instance, $node, (is_int($returned['iteration'] ?? null) ? $returned['iteration'] : 1) + 1, is_string($returned['branch'] ?? null) ? $returned['branch'] : '');

            return $instance;
        });

        $this->advance($followUp);
    }

    /** Withdraws or cancels an open instance (and its sub-workflows). */
    public function cancel(WorkflowInstance $instance, ?User $actor, ?string $reason = null): void
    {
        $this->locked($instance->id, function () use ($instance, $actor, $reason): null {
            $instance = WorkflowInstance::query()->whereKey($instance->id)->lockForUpdate()->firstOrFail();

            if (! $instance->isOpen()) {
                throw new DomainRuleViolation('workflows.closed', __('This has already finished.'));
            }

            Timeline::add($instance, 'cancelled', null, $actor?->id, $reason === null ? [] : ['reason' => $reason]);
            $this->finish($instance, Outcome::Cancelled, InstanceStatus::Cancelled);

            return null;
        });

        foreach (WorkflowInstance::query()->whereIn('parent_step_id', $instance->steps()->select('id'))->get() as $child) {
            if ($child->isOpen()) {
                $this->cancel($child, $actor, $reason);
            }
        }

        $this->flushWake();
    }

    /** Dead-letter view: run a failed step again. */
    public function retry(WorkflowStep $step, ?User $actor): void
    {
        $instance = $this->locked($step->workflow_instance_id, function () use ($step, $actor): WorkflowInstance {
            $step = WorkflowStep::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            $instance = WorkflowInstance::query()->whereKey($step->workflow_instance_id)->lockForUpdate()->firstOrFail();

            if ($step->status !== StepStatus::Failed) {
                throw new DomainRuleViolation('workflows.not_failed', __('Only failed steps can be retried.'));
            }

            $step->forceFill(['status' => StepStatus::Pending, 'attempts' => 0, 'available_at' => $this->now(), 'error' => null])->save();
            $instance->forceFill(['status' => InstanceStatus::Running, 'attention_reason' => null])->save();
            Timeline::add($instance, 'step.retried', $step, $actor?->id);

            return $instance;
        });

        $this->advance($instance);
    }

    /**
     * Dead-letter view: mark a failed step done and continue through the chosen paths.
     *
     * @param  list<string>|null  $handles
     */
    public function skip(WorkflowStep $step, ?User $actor, ?array $handles = null): void
    {
        $followUp = $this->locked($step->workflow_instance_id, function () use ($step, $actor, $handles): ?WorkflowInstance {
            $step = WorkflowStep::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            $instance = WorkflowInstance::query()->whereKey($step->workflow_instance_id)->lockForUpdate()->firstOrFail();

            if ($step->status !== StepStatus::Failed) {
                throw new DomainRuleViolation('workflows.not_failed', __('Only failed steps can be skipped.'));
            }

            $instance->forceFill(['status' => InstanceStatus::Running, 'attention_reason' => null])->save();
            Timeline::add($instance, 'step.skipped', $step, $actor?->id);

            return $this->apply($instance, $step, Graph::from($instance->pinnedVersion()->graph), NodeResult::completed(['skipped' => true], $handles));
        });

        if ($followUp instanceof WorkflowInstance) {
            $this->advance($followUp);
        }

        $this->flushWake();
    }

    /**
     * The minutely sweep (`workflows:tick`): due timers and retries run, reminders go out, overdue
     * tasks escalate. The database is the source of truth, so this is correct after a restart.
     */
    public function tick(): void
    {
        $now = $this->now();

        WorkflowStep::query()
            ->where('status', StepStatus::Waiting->value)
            ->where('node_type', 'delay')
            ->where('available_at', '<=', $now)
            ->update(['status' => StepStatus::Pending->value]);

        $instances = WorkflowStep::query()
            ->where('status', StepStatus::Pending->value)
            ->where(fn (Builder $q) => $q->whereNull('available_at')->orWhere('available_at', '<=', $now))
            ->distinct()
            ->pluck('workflow_instance_id');

        foreach (WorkflowInstance::query()->whereIn('id', $instances)->get() as $instance) {
            $this->advance($instance);
        }

        foreach (WorkflowTask::query()->where('status', TaskStatus::Open->value)->whereNull('reminded_at')->where('remind_at', '<=', $now)->get() as $task) {
            $task->forceFill(['reminded_at' => $now])->save();
            Timeline::add($task->owner(), 'task.reminded', $task->ownerStep(), null, ['task' => $task->ulid]);
            event(new TaskAssigned($task, 'reminder'));
        }

        foreach (WorkflowTask::query()->where('status', TaskStatus::Open->value)->whereNull('escalated_at')->where('due_at', '<=', $now)->get() as $task) {
            $this->escalate($task);
        }
    }

    private function escalate(WorkflowTask $task): void
    {
        $instance = $task->owner();
        $step = $task->ownerStep();
        $config = Graph::from($instance->pinnedVersion()->graph)->node($step->node_id)['config'] ?? [];
        $sla = is_array($config['sla'] ?? null) ? $config['sla'] : [];
        $spec = is_array($sla['escalateTo'] ?? null) ? $sla['escalateTo'] : ['type' => 'assignee_manager'];
        $next = array_values(array_diff($this->resolver->resolve($spec, $instance, $task->assignee_user_id), [$task->assignee_user_id, $instance->requester_user_id]))[0] ?? null;

        if ($next === null) {
            $task->forceFill(['escalated_at' => $this->now()])->save();
            Timeline::add($instance, 'task.overdue', $step, null, ['task' => $task->ulid]);

            return;
        }

        $task->forceFill(['assignee_user_id' => $next, 'escalated_at' => $this->now()])->save();
        Timeline::add($instance, 'task.escalated', $step, null, ['task' => $task->ulid, 'to' => $next]);
        event(new TaskAssigned($task, 'escalated'));
    }

    /**
     * Acts on a handler result. Returns the instance when new steps may be due.
     */
    private function apply(WorkflowInstance $instance, WorkflowStep $step, Graph $graph, NodeResult $result): ?WorkflowInstance
    {
        if ($result->isWaiting()) {
            $step->forceFill(['status' => StepStatus::Waiting, 'available_at' => $result->until, 'due_at' => $result->until ?? $step->due_at])->save();

            return null;
        }

        if (! $result->isCompleted()) {
            return $this->fail($instance, $step, (string) $result->error, $result->retryable);
        }

        $step->forceFill(['status' => StepStatus::Completed, 'output' => $result->output, 'error' => null, 'finished_at' => $this->now()])->save();
        $steps = is_array($instance->context['steps'] ?? null) ? $instance->context['steps'] : [];
        $instance->forceFill(['context' => [...$instance->context, 'steps' => [...$steps, $step->node_id => $result->output]]])->save();

        if ($step->node_type === 'end') {
            $outcome = Outcome::tryFrom(is_string($result->output['outcome'] ?? null) ? $result->output['outcome'] : '') ?? Outcome::Completed;
            Timeline::add($instance, 'completed', $step, null, ['outcome' => $outcome->value]);
            $this->finish($instance, $outcome, InstanceStatus::Completed);

            return null;
        }

        foreach ($graph->outgoing($step->node_id, $result->handles) as $edge) {
            $target = $graph->node($edge['target']);

            if ($target === null) {
                continue;
            }

            if ($target['type'] === 'join') {
                $this->arrive($instance, $graph, $target, $edge['id'], $step->iteration);
            } else {
                $this->activate($instance, $target, $step->iteration, $step->node_type === 'split' ? $edge['id'] : $step->branch_key);
            }
        }

        return $instance;
    }

    /**
     * @param  array{id: string, type: string, config: array<string, mixed>}  $node
     * @param  array<string, mixed>  $input
     */
    private function activate(WorkflowInstance $instance, array $node, int $iteration, string $branch, array $input = []): WorkflowStep
    {
        /** @var WorkflowStep $step */
        $step = WorkflowStep::query()->firstOrCreate(
            ['workflow_instance_id' => $instance->id, 'node_id' => $node['id'], 'iteration' => $iteration, 'branch_key' => $branch],
            ['node_type' => $node['type'], 'status' => StepStatus::Pending, 'available_at' => $this->now(), 'input' => $input === [] ? null : $input],
        );

        return $step;
    }

    /**
     * A branch reached a join: it runs once every inbound edge has arrived.
     *
     * @param  array{id: string, type: string, config: array<string, mixed>}  $join
     */
    private function arrive(WorkflowInstance $instance, Graph $graph, array $join, string $edgeId, int $iteration): void
    {
        /** @var WorkflowStep $step */
        $step = WorkflowStep::query()->firstOrCreate(
            ['workflow_instance_id' => $instance->id, 'node_id' => $join['id'], 'iteration' => $iteration, 'branch_key' => ''],
            ['node_type' => 'join', 'status' => StepStatus::Waiting, 'input' => ['arrived' => []]],
        );
        $arrived = is_array($step->input['arrived'] ?? null) ? array_filter($step->input['arrived'], is_string(...)) : [];
        $arrived = array_values(array_unique([...$arrived, $edgeId]));
        $complete = count($arrived) >= count($graph->incoming($join['id']));

        $step->forceFill([
            'input' => ['arrived' => $arrived],
            'status' => $complete && $step->status === StepStatus::Waiting ? StepStatus::Pending : $step->status,
            'available_at' => $complete ? $this->now() : null,
        ])->save();
    }

    /**
     * Waiting sub-workflow steps without a child start one. Runs outside the parent's lock so a
     * child that finishes at once can wake its parent.
     */
    private function startChildren(int $instanceId): void
    {
        $instance = WorkflowInstance::query()->find($instanceId);

        if (! $instance instanceof WorkflowInstance || $instance->status !== InstanceStatus::Running) {
            return;
        }

        $graph = Graph::from($instance->pinnedVersion()->graph);
        $steps = WorkflowStep::query()->where('workflow_instance_id', $instance->id)->where('node_type', 'subworkflow')->where('status', StepStatus::Waiting->value)->get();

        foreach ($steps as $step) {
            if (WorkflowInstance::query()->where('parent_step_id', $step->id)->exists()) {
                continue;
            }

            $code = $graph->node($step->node_id)['config']['workflow'] ?? null;
            $definition = is_string($code) ? WorkflowDefinition::query()->where('code', $code)->first() : null;

            if ($definition instanceof WorkflowDefinition) {
                $requester = $instance->requester_user_id === null ? null : User::query()->find($instance->requester_user_id);
                $employee = $instance->requester_employee_id === null ? null : Employee::withTrashed()->find($instance->requester_employee_id);
                $context = $instance->context;
                unset($context['steps']);
                $this->start($definition, $context, $requester, $employee, null, $step);
            }
        }
    }

    private function fail(WorkflowInstance $instance, WorkflowStep $step, string $error, bool $retryable): null
    {
        if ($retryable && $step->attempts <= count(self::BACKOFF) && ! $this->simulating) {
            $delay = self::BACKOFF[min(count(self::BACKOFF) - 1, max(0, $step->attempts - 1))];
            $step->forceFill(['status' => StepStatus::Pending, 'available_at' => $this->now()->addSeconds($delay), 'error' => $error])->save();
            // No inline dispatch: we hold the instance lock. workflows:tick runs it when due.

            return null;
        }

        $step->forceFill(['status' => StepStatus::Failed, 'error' => $error, 'finished_at' => $this->now()])->save();
        $instance->forceFill(['status' => InstanceStatus::Attention, 'attention_reason' => $error])->save();
        Timeline::add($instance, 'step.failed', $step, null, ['error' => $error]);

        return null;
    }

    private function finish(WorkflowInstance $instance, Outcome $outcome, InstanceStatus $status): void
    {
        $instance->forceFill(['status' => $status, 'outcome' => $outcome, 'finished_at' => $this->now()])->save();

        WorkflowStep::query()
            ->where('workflow_instance_id', $instance->id)
            ->whereIn('status', [StepStatus::Pending->value, StepStatus::Waiting->value, StepStatus::Running->value])
            ->update(['status' => StepStatus::Cancelled->value, 'finished_at' => $this->now()]);
        WorkflowTask::query()->where('workflow_instance_id', $instance->id)->where('status', TaskStatus::Open->value)->update(['status' => TaskStatus::Cancelled->value]);
        ActionToken::query()->whereIn('workflow_task_id', WorkflowTask::query()->where('workflow_instance_id', $instance->id)->select('id'))->whereNull('used_at')->update(['used_at' => $this->now()]);

        if (! $this->simulating) {
            event(new InstanceFinished($instance));
        }

        // A finished child wakes its parent's sub-workflow step.
        if ($instance->parent_step_id !== null) {
            $parent = WorkflowStep::query()->whereKey($instance->parent_step_id)->first();

            if ($parent instanceof WorkflowStep && $parent->status === StepStatus::Waiting) {
                $parent->forceFill(['status' => StepStatus::Pending, 'available_at' => $this->now()])->save();
                $this->wake[] = $parent->id;
            }
        }
    }

    /** Runs parent steps woken by finished children, after every lock is released. */
    private function flushWake(): void
    {
        while ($this->wake !== []) {
            $stepId = array_shift($this->wake);

            if ($this->simulating) {
                $this->runStep($stepId);
            } else {
                dispatch(new RunStep($stepId));
            }
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function locked(int $instanceId, Closure $callback): mixed
    {
        return Cache::lock("wf:{$instanceId}", 30)->block(10, fn () => $this->db->transaction($callback));
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }
}
