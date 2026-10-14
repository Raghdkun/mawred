<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Workflows\Engine\WorkflowEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workflows:tick')]
#[Description('Run due workflow timers and retries, send reminders and escalate overdue tasks (ADR-0009)')]
final class WorkflowsTick extends Command
{
    public function handle(WorkflowEngine $engine): int
    {
        $engine->tick();

        return self::SUCCESS;
    }
}
