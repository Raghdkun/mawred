<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\AssigneeResolver;
use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;
use App\Domain\Workflows\Engine\TaskFactory;
use App\Domain\Workflows\Engine\Timeline;
use App\Domain\Workflows\Enums\TaskKind;

/**
 * A to-do for someone (e.g. "prepare the laptop"). Leaves through `done` once marked done.
 *
 * Config: {label, instructions, assignee: spec, sla}
 */
final readonly class TaskNode implements NodeHandler
{
    public function __construct(private AssigneeResolver $resolver, private TaskFactory $tasks) {}

    public function type(): string
    {
        return 'task';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        return AssigneeSpec::problems($config['assignee'] ?? null);
    }

    public function handle(StepContext $context): NodeResult
    {
        if ($context->step->tasks()->exists()) {
            return NodeResult::waiting();
        }

        $spec = is_array($context->config['assignee'] ?? null) ? $context->config['assignee'] : [];
        $users = $this->resolver->resolve($spec, $context->instance, cursorKey: $context->instance->workflow_definition_id.':'.$context->step->node_id);

        if ($users === []) {
            $users = $this->resolver->fallback();
            Timeline::add($context->instance, 'assignee.fallback', $context->step);
        }

        if ($users === []) {
            return NodeResult::failed(__('Nobody can take this task.'), retryable: false);
        }

        $this->tasks->create($context, TaskKind::Task, [$users[0]], is_array($context->config['sla'] ?? null) ? $context->config['sla'] : []);

        return NodeResult::waiting();
    }

    public function handles(array $config): array
    {
        return ['done'];
    }
}
