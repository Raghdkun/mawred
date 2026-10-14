<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowInstance;

/**
 * Runs another published workflow as a child and waits for it; leaves through the child's
 * outcome (`approved`, `rejected` or `completed`). The engine starts the child.
 *
 * Config: {workflow: <code>}
 */
final class SubWorkflowNode implements NodeHandler
{
    public function type(): string
    {
        return 'subworkflow';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        $ok = is_string($config['workflow'] ?? null) && WorkflowDefinition::query()->where('code', $config['workflow'])->whereNotNull('published_version_id')->exists();

        return $ok ? [] : [__('Choose a published workflow.')];
    }

    public function handle(StepContext $context): NodeResult
    {
        $child = WorkflowInstance::query()->where('parent_step_id', $context->step->id)->first();

        if ($child instanceof WorkflowInstance && $child->outcome !== null) {
            return NodeResult::completed(['outcome' => $child->outcome->value], [$child->outcome->value]);
        }

        return NodeResult::waiting();
    }

    public function handles(array $config): array
    {
        return ['approved', 'rejected', 'completed'];
    }
}
