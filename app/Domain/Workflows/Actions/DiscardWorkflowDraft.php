<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Enums\VersionStatus;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowVersion;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Throws away unpublished changes; the builder goes back to the published version. */
final readonly class DiscardWorkflowDraft
{
    public function handle(Actor $actor, WorkflowDefinition $definition): void
    {
        if ($definition->published_version_id === null) {
            throw new DomainRuleViolation('workflows.nothing_published', __('Publish this workflow once before discarding changes.'));
        }

        $draft = WorkflowVersion::query()->where('workflow_definition_id', $definition->id)->where('status', VersionStatus::Draft->value)->first();
        $draft?->delete();

        activity('workflows')->causedBy($actor->user)->performedOn($definition)->event('draft.discarded')->log('Workflow draft discarded');
    }
}
