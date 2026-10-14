<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Support\Actors\Actor;

/** Runs a failed step again from the dead-letter view (ADR-0009). */
final readonly class RetryWorkflowStep
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(Actor $actor, WorkflowStep $step): void
    {
        $this->engine->retry($step, $actor->user);
    }
}
