<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Support\Actors\Actor;

/** Moves past a failed step (e.g. a webhook that will never succeed) along its default path. */
final readonly class SkipWorkflowStep
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(Actor $actor, WorkflowStep $step): void
    {
        $this->engine->skip($step, $actor->user);
    }
}
