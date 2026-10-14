<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Events;

use App\Domain\Workflows\Models\WorkflowTask;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A task landed in someone's inbox (new, delegated or escalated). Notifications listen. */
final readonly class TaskAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public WorkflowTask $task, public string $reason = 'assigned') {}
}
