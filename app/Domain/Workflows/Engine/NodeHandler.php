<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

/**
 * One node type (ADR-0009). Handlers are idempotent: running one twice for the same step has no
 * extra effect (side effects key on the step ULID). In simulation they record instead of act.
 */
interface NodeHandler
{
    public function type(): string;

    /**
     * Problems with a node's configuration, checked on publish.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validate(array $config, Graph $graph, string $nodeId): array;

    public function handle(StepContext $context): NodeResult;

    /**
     * Edge handles this node may leave through (validation checks they are wired), or null for one
     * plain outgoing edge set.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>|null
     */
    public function handles(array $config): ?array;
}
