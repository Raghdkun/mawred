<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;

/** Where every instance begins (the request was submitted). */
final class StartNode implements NodeHandler
{
    public function type(): string
    {
        return 'start';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        return $graph->incoming($nodeId) === [] ? [] : [__('The start can\'t have incoming connections.')];
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
