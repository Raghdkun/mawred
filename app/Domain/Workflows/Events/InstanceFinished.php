<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Events;

use App\Domain\Workflows\Models\WorkflowInstance;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** An instance ended (approved, rejected, completed or cancelled), or was returned for changes. */
final readonly class InstanceFinished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance) {}
}
