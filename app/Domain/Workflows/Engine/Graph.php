<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

/**
 * A `mawred.workflow/v1` graph: `{format, nodes: [{id, type, config}], edges: [{id, source,
 * target, sourceHandle?}]}`. Read-only view with lookups the engine and validator need.
 */
final readonly class Graph
{
    public const string FORMAT = 'mawred.workflow/v1';

    /**
     * @param  array<string, array{id: string, type: string, config: array<string, mixed>}>  $nodes
     * @param  list<array{id: string, source: string, target: string, sourceHandle: string|null}>  $edges
     */
    public function __construct(public array $nodes, public array $edges) {}

    /**
     * @param  array<mixed>  $graph
     */
    public static function from(array $graph): self
    {
        $nodes = [];
        $edges = [];

        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (is_array($node) && is_string($node['id'] ?? null) && is_string($node['type'] ?? null)) {
                $config = [];

                foreach (is_array($node['config'] ?? null) ? $node['config'] : [] as $name => $value) {
                    $config[(string) $name] = $value;
                }

                $nodes[$node['id']] = ['id' => $node['id'], 'type' => $node['type'], 'config' => $config];
            }
        }

        foreach (is_array($graph['edges'] ?? null) ? $graph['edges'] : [] as $edge) {
            if (is_array($edge) && is_string($edge['id'] ?? null) && is_string($edge['source'] ?? null) && is_string($edge['target'] ?? null)) {
                $edges[] = [
                    'id' => $edge['id'],
                    'source' => $edge['source'],
                    'target' => $edge['target'],
                    'sourceHandle' => is_string($edge['sourceHandle'] ?? null) && $edge['sourceHandle'] !== '' ? $edge['sourceHandle'] : null,
                ];
            }
        }

        return new self($nodes, $edges);
    }

    /**
     * @return array{id: string, type: string, config: array<string, mixed>}|null
     */
    public function node(string $id): ?array
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * @return list<array{id: string, type: string, config: array<string, mixed>}>
     */
    public function nodesOfType(string $type): array
    {
        return array_values(array_filter($this->nodes, fn (array $node): bool => $node['type'] === $type));
    }

    /**
     * Outgoing edges, optionally only those leaving through the given handles.
     *
     * @param  list<string>|null  $handles  null = every edge
     * @return list<array{id: string, source: string, target: string, sourceHandle: string|null}>
     */
    public function outgoing(string $nodeId, ?array $handles = null): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (array $edge): bool => $edge['source'] === $nodeId && ($handles === null || in_array($edge['sourceHandle'] ?? 'out', $handles, true)),
        ));
    }

    /**
     * @return list<array{id: string, source: string, target: string, sourceHandle: string|null}>
     */
    public function incoming(string $nodeId): array
    {
        return array_values(array_filter($this->edges, fn (array $edge): bool => $edge['target'] === $nodeId));
    }

    /**
     * Node ids reachable from a node by following edges.
     *
     * @return list<string>
     */
    public function reachableFrom(string $nodeId): array
    {
        $seen = [$nodeId => true];
        $queue = [$nodeId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->outgoing($current) as $edge) {
                if (! isset($seen[$edge['target']])) {
                    $seen[$edge['target']] = true;
                    $queue[] = $edge['target'];
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * The checksum covers nodes and edges only; layout (positions) is stored apart.
     *
     * @param  array<mixed>  $graph
     */
    public static function checksum(array $graph): string
    {
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];

        return hash('sha256', (string) json_encode(['nodes' => $nodes, 'edges' => $edges]));
    }
}
