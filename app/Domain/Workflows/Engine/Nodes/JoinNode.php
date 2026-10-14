<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;

/**
 * Waits for every inbound branch. The engine activates it only once all have arrived; if any
 * branch is rejected the instance has already ended through that branch's End.
 */
final class JoinNode implements NodeHandler
{
    public function type(): string
    {
        return 'join';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        return count($graph->incoming($nodeId)) >= 2 ? [] : [__('A join needs at least two incoming branches.')];
    }

    public function handle(StepContext $context): NodeResult
    {
        return NodeResult::completed();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function handles(array $config): ?array
    {
        return null;
    }
}
