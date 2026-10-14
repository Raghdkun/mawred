<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;

/** Runs every outgoing branch at once; a Join waits for them all. */
final class SplitNode implements NodeHandler
{
    public function type(): string
    {
        return 'split';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        return count($graph->outgoing($nodeId)) >= 2 ? [] : [__('A parallel split needs at least two branches.')];
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
