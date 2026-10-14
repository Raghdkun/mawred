<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Workflows\Engine\Timeline;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Events\TaskAssigned;
use App\Domain\Workflows\Models\ActionToken;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Hands an open task to someone else (handoff 13 delegate dialog). The original assignee is kept;
 * earlier approve/reject links stop working.
 */
final readonly class DelegateTask
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(WorkflowTask $task, User $from, User $to, ?string $comment = null): WorkflowTask
    {
        if ($task->status !== TaskStatus::Open) {
            throw new DomainRuleViolation('workflows.task_closed', __('This has already been decided.'));
        }

        if ($to->id === $from->id || $to->id === $task->owner()->requester_user_id) {
            throw new DomainRuleViolation('workflows.delegate_invalid', __('Choose someone else to delegate to.'), ['to' => [__('Choose someone else to delegate to.')]]);
        }

        return $this->db->transaction(function () use ($task, $from, $to, $comment): WorkflowTask {
            $task->forceFill(['assignee_user_id' => $to->id, 'delegated_from_user_id' => $from->id])->save();
            ActionToken::query()->where('workflow_task_id', $task->id)->whereNull('used_at')->update(['used_at' => now()]);
            Timeline::add($task->owner(), 'task.delegated', $task->ownerStep(), $from->id, ['task' => $task->ulid, 'to' => $to->id, 'from' => $from->id, 'comment' => $comment]);
            event(new TaskAssigned($task, 'delegated'));

            return $task;
        });
    }
}
