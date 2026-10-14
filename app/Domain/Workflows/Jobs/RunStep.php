<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Jobs;

use App\Domain\Workflows\Engine\WorkflowEngine;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Runs one workflow step (ADR-0009). Unique per step while queued; the engine re-checks the step
 * is still pending under its lock, so a duplicate run is a no-op. Retries are the engine's
 * (backoff on the step row, swept by `workflows:tick`), so the job itself runs once.
 */
#[Queue('workflows')]
#[Tries(1)]
#[UniqueFor(900)]
final class RunStep implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $stepId) {}

    public function uniqueId(): string
    {
        return (string) $this->stepId;
    }

    public function handle(WorkflowEngine $engine): void
    {
        $engine->runStep($this->stepId);
    }
}
