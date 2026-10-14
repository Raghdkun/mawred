<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Support\Rules\Rules;

/**
 * Publish-time checks (architecture §6): one start, at least one end, no cycles, every node
 * reachable and able to finish, branches wired, node config valid, and conditions only reading
 * fields the bound form has. Problems are keyed by node id (`_graph` for the whole graph).
 */
final readonly class GraphValidator
{
    public function __construct(private NodeRegistry $nodes) {}

    /**
     * @param  array<mixed>  $graph
     * @param  list<string>|null  $formFields  field keys of the bound form, when known
     * @return array<string, list<string>>
     */
    public function problems(array $graph, ?array $formFields = null): array
    {
        $problems = [];
        $add = function (string $at, string $message) use (&$problems): void {
            $problems[$at][] = $message;
        };

        if (($graph['format'] ?? null) !== Graph::FORMAT) {
            $add('_graph', __('This is not a Mawred workflow.'));

            return $problems;
        }

        $g = Graph::from($graph);

        foreach ($g->nodes as $id => $node) {
            if (! $this->nodes->has($node['type'])) {
                $add($id, __('Unknown step type.'));
            }
        }

        $starts = $g->nodesOfType('start');
        $ends = $g->nodesOfType('end');

        if (count($starts) !== 1) {
            $add('_graph', __('A workflow needs exactly one start.'));
        }

        if ($ends === []) {
            $add('_graph', __('A workflow needs at least one end.'));
        }

        foreach ($g->edges as $edge) {
            if ($g->node($edge['source']) === null || $g->node($edge['target']) === null) {
                $add('_graph', __('A connection points to a step that no longer exists.'));
            }
        }

        if ($problems !== [] || $starts === []) {
            return $problems;
        }

        if ($this->hasCycle($g)) {
            $add('_graph', __('This workflow loops back on itself. Use return for changes instead.'));
        }

        $reachable = $g->reachableFrom($starts[0]['id']);
        $endIds = array_column($ends, 'id');

        foreach ($g->nodes as $id => $node) {
            if (! in_array($id, $reachable, true)) {
                $add($id, __('Nothing leads to this step.'));
            }

            if ($node['type'] !== 'end' && array_intersect($g->reachableFrom($id), $endIds) === []) {
                $add($id, __('This step never reaches an end.'));
            }

            if (! $this->nodes->has($node['type'])) {
                continue;
            }

            $handler = $this->nodes->get($node['type']);
            $handles = $handler->handles($node['config']);
            $outgoing = $g->outgoing($id);

            if ($node['type'] === 'end' && $outgoing !== []) {
                $add($id, __('An end can\'t lead anywhere.'));
            } elseif ($handles === null && $node['type'] !== 'end' && $outgoing === []) {
                $add($id, __('Connect this step to the next one.'));
            } elseif ($handles !== null && $handles !== [] && $node['type'] !== 'subworkflow') {
                foreach ($handles as $handle) {
                    if ($g->outgoing($id, [$handle]) === []) {
                        $add($id, __('Connect the ":handle" path.', ['handle' => $this->handleLabel($handle)]));
                    }
                }
            } elseif ($node['type'] === 'subworkflow' && $outgoing === []) {
                $add($id, __('Connect this step to the next one.'));
            }

            foreach ($handler->validate($node['config'], $g, $id) as $problem) {
                $add($id, $problem);
            }

            if ($formFields !== null && $node['type'] === 'condition') {
                foreach (Rules::refs($node['config']['rule'] ?? null) as $ref) {
                    if (str_starts_with($ref, 'field.') && ! in_array(explode('.', $ref)[1] ?? '', $formFields, true)) {
                        $add($id, __('This rule uses a field that does not exist: :field.', ['field' => substr($ref, 6)]));
                    }
                }
            }
        }

        return $problems;
    }

    private function handleLabel(string $handle): string
    {
        return match ($handle) {
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'true' => __('Yes'),
            'false' => __('No'),
            'done' => __('Done'),
            default => $handle,
        };
    }

    private function hasCycle(Graph $graph): bool
    {
        $state = [];
        $visit = function (string $id) use (&$visit, &$state, $graph): bool {
            $state[$id] = 1;

            foreach ($graph->outgoing($id) as $edge) {
                $next = $edge['target'];

                if (($state[$next] ?? 0) === 1 || (($state[$next] ?? 0) === 0 && $visit($next))) {
                    return true;
                }
            }

            $state[$id] = 2;

            return false;
        };

        return array_any(array_keys($graph->nodes), fn ($id): bool => ($state[$id] ?? 0) === 0 && $visit($id));
    }
}
