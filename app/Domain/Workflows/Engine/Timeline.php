<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\Workflows\Models\WorkflowTimelineEntry;

/** Appends to an instance's human-readable history. */
final class Timeline
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function add(WorkflowInstance $instance, string $event, ?WorkflowStep $step = null, ?int $actorId = null, array $data = []): void
    {
        WorkflowTimelineEntry::query()->create([
            'workflow_instance_id' => $instance->id,
            'workflow_step_id' => $step?->id,
            'event' => $event,
            'actor_user_id' => $actorId,
            'data' => $data === [] ? null : $data,
        ]);
    }
}
