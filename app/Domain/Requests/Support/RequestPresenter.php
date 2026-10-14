<?php

declare(strict_types=1);

namespace App\Domain\Requests\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\AnswerLabels;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Data\RequestRowData;
use App\Domain\Requests\Data\TaskData;
use App\Domain\Requests\Data\TimelineEntryData;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestComment;
use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Enums\TaskKind;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Domain\Workflows\Models\WorkflowTimelineEntry;
use Illuminate\Support\Facades\Gate;

/**
 * Builds what the request detail and the inbox show: answers labelled from the pinned form
 * version, the timeline with names, comments, open tasks and what the viewer may do.
 */
final class RequestPresenter
{
    /** @var array<int, string> */
    private array $names = [];

    /**
     * @return array<string, mixed>
     */
    public function detail(EmployeeRequest $request, User $viewer): array
    {
        $request->loadMissing(['type', 'requester', 'submission.version']);
        $submission = $request->submission()->firstOrFail();
        $schema = $submission->pinnedVersion()->schema;
        $instance = $request->instance()->first();
        $locale = app()->getLocale();

        return [
            'request' => RequestRowData::fromRequest($request),
            'sensitive' => $request->requestType()->is_sensitive,
            'schema' => $schema,
            'answers' => $submission->data,
            'display' => AnswerLabels::for($schema, $submission->data, $locale),
            'timeline' => $instance instanceof WorkflowInstance ? $this->timeline($instance) : [],
            'comments' => array_values($request->comments()->with('author')->orderBy('id')->get()->map(fn (RequestComment $comment): array => [
                'id' => $comment->ulid,
                'author' => $this->name($comment->user_id),
                'body' => $comment->body,
                'at' => $comment->created_at->toIso8601String(),
            ])->all()),
            'tasks' => $instance instanceof WorkflowInstance ? array_values(WorkflowTask::query()
                ->where('workflow_instance_id', $instance->id)->where('status', TaskStatus::Open->value)->get()
                ->map(fn (WorkflowTask $task): TaskData => $this->task($task, null, $viewer))->all()) : [],
            'can' => [
                'withdraw' => $request->status->isOpen() && Gate::forUser($viewer)->allows('withdraw', $request),
                'resubmit' => $request->status->value === 'returned' && $request->requester_user_id === $viewer->id,
                'comment' => Gate::forUser($viewer)->allows('comment', $request),
            ],
        ];
    }

    /**
     * @return list<TimelineEntryData>
     */
    public function timeline(WorkflowInstance $instance): array
    {
        $graph = Graph::from($instance->pinnedVersion()->graph);
        $steps = WorkflowStep::query()->where('workflow_instance_id', $instance->id)->pluck('node_id', 'id');
        $locale = app()->getLocale();

        return array_values(WorkflowTimelineEntry::query()->where('workflow_instance_id', $instance->id)->orderBy('id')->get()
            ->reject(fn (WorkflowTimelineEntry $entry): bool => $entry->event === 'assignee.fallback')
            ->map(function (WorkflowTimelineEntry $entry) use ($graph, $steps, $locale): TimelineEntryData {
                $data = $entry->data ?? [];
                $nodeId = $entry->workflow_step_id === null ? null : $steps[$entry->workflow_step_id] ?? null;
                $label = is_string($nodeId) ? ($graph->node($nodeId)['config']['label'] ?? null) : null;
                $subject = is_int($data['assignee'] ?? null) ? $this->name($data['assignee']) : (is_int($data['to'] ?? null) ? $this->name($data['to']) : null);

                return new TimelineEntryData(
                    event: $entry->event,
                    at: $entry->created_at->toIso8601String(),
                    actor: $entry->actor_user_id === null ? null : $this->name($entry->actor_user_id),
                    subject: $subject,
                    comment: is_string($data['comment'] ?? null) ? $data['comment'] : (is_string($data['reason'] ?? null) ? $data['reason'] : null),
                    via: is_string($data['via'] ?? null) ? $data['via'] : null,
                    data: ['step' => is_array($label) ? $this->localised($label, $locale) : null, 'outcome' => $data['outcome'] ?? null],
                );
            })->all());
    }

    public function task(WorkflowTask $task, ?EmployeeRequest $request, User $viewer): TaskData
    {
        $instance = $task->owner();
        $step = $task->ownerStep();
        $config = Graph::from($instance->pinnedVersion()->graph)->node($step->node_id)['config'] ?? [];
        $label = $this->labels($config['label'] ?? null);
        $decisions = $task->kind === TaskKind::Task ? ['done'] : ['approved', 'rejected', ...(($config['allowReturn'] ?? true) === false ? [] : ['returned'])];
        $request ??= $instance->subject_type === EmployeeRequest::class ? EmployeeRequest::query()->with(['type', 'requester'])->find($instance->subject_id) : null;

        return new TaskData(
            id: $task->ulid,
            kind: $task->kind->value,
            step: $label,
            assignedAt: $task->created_at->toIso8601String(),
            dueAt: $task->due_at?->toIso8601String(),
            overdue: $task->due_at !== null && $task->due_at->isPast(),
            delegatedFrom: $task->delegated_from_user_id === null ? null : $this->name($task->delegated_from_user_id),
            decisions: $task->assignee_user_id === $viewer->id && $task->status === TaskStatus::Open ? $decisions : [],
            request: $request instanceof EmployeeRequest ? RequestRowData::fromRequest($request) : null,
        );
    }

    /**
     * @param  array<mixed>  $label
     */
    private function localised(array $label, string $locale): string
    {
        $labels = $this->labels($label);

        return $labels[$locale] ?? $labels['en'] ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function labels(mixed $label): array
    {
        $labels = [];

        foreach (is_array($label) ? $label : [] as $locale => $text) {
            if (is_string($locale) && is_string($text)) {
                $labels[$locale] = $text;
            }
        }

        return $labels;
    }

    private function name(?int $userId): string
    {
        if ($userId === null) {
            return '';
        }

        return $this->names[$userId] ??= Employee::query()->where('user_id', $userId)->first()?->displayName(app()->getLocale())
            ?? User::withTrashed()->find($userId)->name ?? '';
    }
}
