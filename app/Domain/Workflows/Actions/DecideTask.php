<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\DecisionChannel;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Errors\DomainRuleViolation;

/**
 * A person decides a task in the app, the API or through a link. Only the current assignee can.
 */
final readonly class DecideTask
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(WorkflowTask $task, User $actor, Decision $decision, ?string $comment = null, DecisionChannel $channel = DecisionChannel::Web): void
    {
        if ($task->assignee_user_id !== $actor->id) {
            throw new DomainRuleViolation('workflows.not_assignee', __('This is assigned to someone else.'));
        }

        $this->engine->decide($task, $decision, $actor, $comment === null ? null : trim($comment), $channel);
    }
}
