<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Enums\VersionStatus;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Domain\Workflows\Models\WorkflowVersion;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * Test-runs a graph (handoff 15 simulator, ADR-0009): the real engine in simulation mode inside a
 * transaction that is always rolled back, so nothing is persisted, notified or posted. Approval
 * nodes take the decision chosen per node (default: approved).
 */
final readonly class Simulator
{
    private const int MAX_DECISIONS = 50;

    public function __construct(private ConnectionInterface $db, private WorkflowEngine $engine, private GraphValidator $validator) {}

    /**
     * @param  array<mixed>  $graph
     * @param  array<string, mixed>  $fields  sample form answers
     * @param  array<string, string>  $decisions  node id => approved|rejected|done
     * @param  array<string, mixed>  $context  requester + ctx (FormContext)
     * @return array{problems: array<string, list<string>>, path: list<array{node: string, type: string, status: string, assignees: list<string>, decision: string|null}>, outcome: string|null, status: string|null}
     */
    public function run(array $graph, array $fields, array $decisions, ?Employee $requester, array $context = []): array
    {
        $problems = $this->validator->problems($graph);

        if ($problems !== []) {
            return ['problems' => $problems, 'path' => [], 'outcome' => null, 'status' => null];
        }

        $result = ['problems' => [], 'path' => [], 'outcome' => null, 'status' => null];
        $this->db->beginTransaction();
        $this->engine->simulating = true;

        try {
            $definition = new WorkflowDefinition(['code' => 'SIM_'.bin2hex(random_bytes(4)), 'name' => ['en' => 'Simulation']]);
            $definition->save();
            $version = new WorkflowVersion(['workflow_definition_id' => $definition->id, 'number' => 1, 'status' => VersionStatus::Published, 'graph' => $graph]);
            $version->save();
            $user = $requester?->user_id === null ? null : User::query()->find($requester->user_id);
            $instance = $this->engine->start($definition, [...$context, 'field' => $fields], $user, $requester, null, null, $version);

            for ($i = 0; $i < self::MAX_DECISIONS; $i++) {
                $task = WorkflowTask::query()->where('workflow_instance_id', $instance->id)->where('status', TaskStatus::Open->value)->orderBy('id')->first();

                if (! $task instanceof WorkflowTask) {
                    break;
                }

                $node = $task->ownerStep()->node_id;
                $decision = Decision::tryFrom($decisions[$node] ?? '') ?? ($task->kind->value === 'task' ? Decision::Done : Decision::Approved);
                $this->engine->decide($task, $decision, $task->assignee()->first(), $decision === Decision::Approved || $decision === Decision::Done ? null : 'Simulation');
            }

            $instance = WorkflowInstance::query()->findOrFail($instance->id);
            $result['path'] = $this->path($instance);
            $result['outcome'] = $instance->outcome?->value;
            $result['status'] = $instance->status->value;
        } catch (Throwable $e) {
            $result['problems'] = ['_graph' => [$e->getMessage()]];
        } finally {
            $this->engine->simulating = false;
            $this->db->rollBack();
        }

        return $result;
    }

    /**
     * @return list<array{node: string, type: string, status: string, assignees: list<string>, decision: string|null}>
     */
    private function path(WorkflowInstance $instance): array
    {
        $locale = app()->getLocale();

        return array_values(WorkflowStep::query()->where('workflow_instance_id', $instance->id)->orderBy('id')->get()->map(function (WorkflowStep $step) use ($locale): array {
            $tasks = $step->tasks()->with('assignee')->get();

            return [
                'node' => $step->node_id,
                'type' => $step->node_type,
                'status' => $step->status->value,
                'assignees' => array_values($tasks->map(fn (WorkflowTask $task): string => Employee::query()->where('user_id', $task->assignee_user_id)->first()?->displayName($locale) ?? $task->assignee->name ?? '?')->all()),
                'decision' => is_string($step->output['decision'] ?? null) ? $step->output['decision'] : null,
            ];
        })->all());
    }
}
