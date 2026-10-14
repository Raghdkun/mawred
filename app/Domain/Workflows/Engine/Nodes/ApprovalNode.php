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
 * Asks people to approve. `mode`: `any` (first decision wins) or `all` (everyone approves; one
 * rejection rejects). Leaves through `approved` or `rejected`; "return for changes" sends the
 * request back and re-enters this node on resubmission. The requester never approves their own.
 *
 * Config: {label, assignee: spec, mode, sla: {hours, reminderHours, escalateTo: spec}, allowReturn}
 */
final readonly class ApprovalNode implements NodeHandler
{
    public function __construct(private AssigneeResolver $resolver, private TaskFactory $tasks) {}

    public function type(): string
    {
        return 'approval';
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
        $users = array_values(array_diff(
            $this->resolver->resolve($spec, $context->instance, cursorKey: $context->instance->workflow_definition_id.':'.$context->step->node_id),
            [$context->instance->requester_user_id],
        ));

        if ($users === []) {
            $users = array_values(array_diff($this->resolver->fallback(), [$context->instance->requester_user_id]));
            Timeline::add($context->instance, 'assignee.fallback', $context->step);
        }

        if ($users === []) {
            return NodeResult::failed(__('Nobody can approve this step.'), retryable: false);
        }

        $sla = is_array($context->config['sla'] ?? null) ? $context->config['sla'] : [];
        $this->tasks->create($context, TaskKind::Approval, $users, $sla);

        return NodeResult::waiting();
    }

    public function handles(array $config): array
    {
        return ['approved', 'rejected'];
    }
}
