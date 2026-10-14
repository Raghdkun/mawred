<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Stops a running or stuck instance; open tasks are cancelled and the subject is told. */
final readonly class CancelWorkflowInstance
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(Actor $actor, WorkflowInstance $instance, string $reason): void
    {
        if (! $instance->isOpen()) {
            throw new DomainRuleViolation('workflows.not_open', __('This workflow has already finished.'));
        }

        $this->engine->cancel($instance, $actor->user, $reason);
    }
}
