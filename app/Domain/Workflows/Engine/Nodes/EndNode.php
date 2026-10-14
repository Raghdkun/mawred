<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;
use App\Domain\Workflows\Enums\Outcome;

/** Ends the instance with an outcome (approved, rejected or completed). The engine closes it. */
final class EndNode implements NodeHandler
{
    public function type(): string
    {
        return 'end';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        $outcome = $config['outcome'] ?? 'completed';

        return is_string($outcome) && in_array($outcome, ['approved', 'rejected', 'completed'], true) ? [] : [__('Choose how this path ends.')];
    }

    public function handle(StepContext $context): NodeResult
    {
        $outcome = is_string($context->config['outcome'] ?? null) ? $context->config['outcome'] : Outcome::Completed->value;

        return NodeResult::completed(['outcome' => $outcome], []);
    }

    public function handles(array $config): array
    {
        return [];
    }
}
