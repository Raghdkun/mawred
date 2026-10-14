<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Enums\VersionStatus;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowVersion;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Saves the canvas into the draft. Drafts may be incomplete; full validation runs on publish.
 */
final readonly class SaveWorkflowDraft
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<mixed>  $graph
     * @param  array<mixed>|null  $layout
     */
    public function handle(Actor $actor, WorkflowDefinition $definition, array $graph, ?array $layout = null): WorkflowVersion
    {
        if (($graph['format'] ?? null) !== Graph::FORMAT || ! is_array($graph['nodes'] ?? null) || ! is_array($graph['edges'] ?? null) || count($graph['nodes']) > 200) {
            throw new DomainRuleViolation('workflows.graph_invalid', __('This is not a Mawred workflow.'), ['graph' => [__('This is not a Mawred workflow.')]]);
        }

        return $this->db->transaction(function () use ($actor, $definition, $graph, $layout): WorkflowVersion {
            WorkflowDefinition::query()->whereKey($definition->id)->lockForUpdate()->first();

            /** @var WorkflowVersion $draft */
            $draft = $definition->draft()->first() ?? $definition->versions()->create([
                'number' => $definition->versions()->count() + 1,
                'status' => VersionStatus::Draft,
                'graph' => $graph,
            ]);
            $draft->forceFill(['graph' => $graph, 'layout' => $layout])->save();

            activity('workflows')->causedBy($actor->user)->performedOn($definition)->event('draft.saved')
                ->withProperties(['version' => $draft->number])->log('Workflow draft saved');

            return $draft;
        });
    }
}
