<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Enums\TaskKind;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Events\TaskAssigned;
use App\Domain\Workflows\Models\Delegation;
use App\Domain\Workflows\Models\WorkflowTask;
use Carbon\CarbonImmutable;

/**
 * Creates human tasks for a step: applies active delegations (the delegate gets it, the original
 * is kept), sets the SLA due time and reminder, and records the assignment.
 */
final readonly class TaskFactory
{
    /**
     * @param  list<int>  $userIds
     * @param  array<mixed>  $sla  {hours?: int, reminderHours?: int}
     * @return list<WorkflowTask>
     */
    public function create(StepContext $context, TaskKind $kind, array $userIds, array $sla): array
    {
        $tasks = [];
        $hours = is_int($sla['hours'] ?? null) && $sla['hours'] > 0 ? $sla['hours'] : null;
        $reminder = is_int($sla['reminderHours'] ?? null) && $sla['reminderHours'] > 0 ? $sla['reminderHours'] : null;

        foreach ($userIds as $userId) {
            $delegate = $this->delegateFor($userId, $context->instance->workflow_definition_id, $context->now);
            $task = new WorkflowTask([
                'workflow_instance_id' => $context->instance->id,
                'workflow_step_id' => $context->step->id,
                'kind' => $kind,
                'assignee_user_id' => $delegate ?? $userId,
                'original_assignee_user_id' => $userId,
                'delegated_from_user_id' => $delegate === null ? null : $userId,
                'status' => TaskStatus::Open,
                'due_at' => $hours === null ? null : $context->now->addHours($hours),
                'remind_at' => $reminder === null ? null : $context->now->addHours($reminder),
            ]);
            $task->save();

            Timeline::add($context->instance, $delegate === null ? 'task.assigned' : 'task.delegated', $context->step, null, [
                'task' => $task->ulid,
                'assignee' => $task->assignee_user_id,
                ...($delegate === null ? [] : ['from' => $userId]),
            ]);

            if (! $context->simulating) {
                event(new TaskAssigned($task, $delegate === null ? 'assigned' : 'delegated'));
            }

            $tasks[] = $task;
        }

        return $tasks;
    }

    /** The delegate for a user on a date, if a delegation covers it (and this workflow). */
    public function delegateFor(int $userId, int $definitionId, CarbonImmutable $now): ?int
    {
        $today = $now->toDateString();
        $delegation = Delegation::query()
            ->where('from_user_id', $userId)
            ->where('starts_on', '<=', $today)
            ->where('ends_on', '>=', $today)
            ->latest('id')
            ->get()
            ->first(fn (Delegation $d): bool => $d->definition_ids === null || $d->definition_ids === [] || in_array($definitionId, $d->definition_ids, true));

        return $delegation?->to_user_id;
    }
}
