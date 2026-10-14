<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\GraphValidator;
use App\Domain\Workflows\Enums\VersionStatus;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowVersion;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Publishes the draft after full validation. New instances start on it; running ones keep the
 * version they started with (ADR-0009).
 */
final readonly class PublishWorkflow
{
    public function __construct(private ConnectionInterface $db, private GraphValidator $validator) {}

    /**
     * @param  list<string>|null  $formFields  the bound form's field keys, to check conditions
     */
    public function handle(Actor $actor, WorkflowDefinition $definition, ?array $formFields = null): WorkflowVersion
    {
        return $this->db->transaction(function () use ($actor, $definition, $formFields): WorkflowVersion {
            WorkflowDefinition::query()->whereKey($definition->id)->lockForUpdate()->first();
            $draft = $definition->draft()->first();

            if (! $draft instanceof WorkflowVersion) {
                throw new DomainRuleViolation('workflows.no_draft', __('There are no changes to publish.'));
            }

            $problems = $this->validator->problems($draft->graph, $formFields);

            if ($problems !== []) {
                $details = [];

                foreach ($problems as $node => $messages) {
                    $details["graph.{$node}"] = $messages;
                }

                throw new DomainRuleViolation('workflows.graph_invalid', __('The workflow has problems to fix before it can be published.'), $details);
            }

            $definition->versions()->where('status', VersionStatus::Published->value)->update(['status' => VersionStatus::Archived->value]);
            $draft->forceFill([
                'status' => VersionStatus::Published,
                'checksum' => Graph::checksum($draft->graph),
                'published_at' => now(),
                'published_by' => $actor->user?->id,
            ])->save();
            $definition->forceFill(['published_version_id' => $draft->id])->save();

            activity('workflows')->causedBy($actor->user)->performedOn($definition)->event('published')
                ->withProperties(['version' => $draft->number])->log('Workflow published');

            return $draft;
        });
    }
}
