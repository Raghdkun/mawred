<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\Notifications\Support\TemplateRenderer;
use App\Domain\Notifications\Support\WorkflowSubject;
use App\Domain\Workflows\Actions\IssueActionLinks;
use App\Domain\Workflows\Enums\DecisionChannel;
use App\Domain\Workflows\Enums\TaskKind;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Events\TaskAssigned;
use App\Domain\Workflows\Models\WorkflowTask;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells the assignee a task is theirs (new, delegated, escalated) or still waiting (reminder).
 * Approval emails carry one-tap approve / reject links (ADR-0009).
 */
final readonly class SendTaskNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationRouter $router,
        private TemplateRenderer $templates,
        private WorkflowSubject $subjects,
        private IssueActionLinks $links,
    ) {}

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function handle(TaskAssigned $event): void
    {
        $task = WorkflowTask::query()->find($event->task->id);

        if (! $task instanceof WorkflowTask || $task->status !== TaskStatus::Open) {
            return;
        }

        $user = User::query()->find($task->assignee_user_id);

        if (! $user instanceof User) {
            return;
        }

        $instance = $task->owner();
        $request = $this->subjects->request($instance);
        $name = match ($event->reason) {
            'reminder' => 'approvals.task_reminder',
            'escalated' => 'approvals.task_escalated',
            'delegated' => 'approvals.task_delegated',
            default => 'approvals.task_assigned',
        };
        $url = route('approvals.index', ['task' => $task->ulid]);

        $this->router->send($user, new NotificationMessage(
            event: $name,
            category: NotificationCategory::Approvals,
            content: fn (string $channel, string $locale): array => $this->templates->render($name, $channel, $locale, [
                ...$this->subjects->variables($instance, $request, $user, $locale),
                'link' => $url,
                'due_at' => $task->due_at?->toIso8601String() ?? '',
            ]),
            url: $url,
            sourceKey: $task->ulid.':'.$event->reason,
            actions: $task->kind === TaskKind::Approval ? fn (string $channel): array => $this->links->handle($task, $channel === 'whatsapp' ? DecisionChannel::WhatsApp : DecisionChannel::Email) : null,
        ));
    }
}
