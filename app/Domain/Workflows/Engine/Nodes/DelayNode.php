<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;

/**
 * Waits a number of hours. The time is stored on the step and swept by `workflows:tick`, so it
 * survives restarts and a flushed queue.
 *
 * Config: {hours}
 */
final class DelayNode implements NodeHandler
{
    public function type(): string
    {
        return 'delay';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        return is_int($config['hours'] ?? null) && $config['hours'] >= 1 && $config['hours'] <= 24 * 90 ? [] : [__('Wait between 1 hour and 90 days.')];
    }

    public function handle(StepContext $context): NodeResult
    {
        $until = $context->step->due_at ?? $context->now->addHours(is_int($context->config['hours'] ?? null) ? $context->config['hours'] : 1);

        return $context->now->greaterThanOrEqualTo($until) || $context->simulating
            ? NodeResult::completed(['waited_until' => $until->toIso8601String()])
            : NodeResult::waiting($until);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function handles(array $config): ?array
    {
        return null;
    }
}
