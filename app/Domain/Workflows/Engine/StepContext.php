<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowStep;
use Carbon\CarbonImmutable;

/**
 * Everything a node handler may read. Time comes from `$now` (injected), never the wall clock,
 * so a handler is deterministic for the same inputs.
 */
final readonly class StepContext
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public WorkflowInstance $instance,
        public WorkflowStep $step,
        public Graph $graph,
        public array $config,
        public CarbonImmutable $now,
        public bool $simulating = false,
    ) {}

    /**
     * The rules context for this instance: form answers, requester, ctx and step decisions.
     *
     * @return array<string, mixed>
     */
    public function rulesContext(): array
    {
        $context = $this->instance->context;

        return [
            'field' => is_array($context['field'] ?? null) ? $context['field'] : [],
            'requester' => is_array($context['requester'] ?? null) ? $context['requester'] : null,
            'ctx' => is_array($context['ctx'] ?? null) ? $context['ctx'] : [],
            'steps' => is_array($context['steps'] ?? null) ? $context['steps'] : [],
        ];
    }
}
