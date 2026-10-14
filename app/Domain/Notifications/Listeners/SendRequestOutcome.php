<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\Notifications\Support\TemplateRenderer;
use App\Domain\Notifications\Support\WorkflowSubject;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\Outcome;
use App\Domain\Workflows\Events\InstanceFinished;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowTask;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells the requester how their request ended, or that it came back for changes. A request they
 * withdrew themselves needs no message.
 */
final readonly class SendRequestOutcome implements ShouldQueue
{
    public function __construct(
        private NotificationRouter $router,
        private TemplateRenderer $templates,
        private WorkflowSubject $subjects,
    ) {}

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function handle(InstanceFinished $event): void
    {
        $instance = WorkflowInstance::query()->find($event->instance->id);

        if (! $instance instanceof WorkflowInstance || $instance->requester_user_id === null) {
            return;
        }

        $name = match (true) {
            $instance->status === InstanceStatus::Returned => 'requests.returned',
            $instance->outcome === Outcome::Approved => 'requests.approved',
            $instance->outcome === Outcome::Rejected => 'requests.rejected',
            $instance->outcome === Outcome::Completed => 'requests.completed',
            default => null,
        };
        $user = User::query()->find($instance->requester_user_id);

        if ($name === null || ! $user instanceof User) {
            return;
        }

        $request = $this->subjects->request($instance);
        $comment = WorkflowTask::query()->where('workflow_instance_id', $instance->id)->whereNotNull('decided_at')->latest('decided_at')->latest('id')->value('comment');
        $url = $this->subjects->url($request);

        $this->router->send($user, new NotificationMessage(
            event: $name,
            category: NotificationCategory::Requests,
            content: fn (string $channel, string $locale): array => $this->templates->render($name, $channel, $locale, [
                ...$this->subjects->variables($instance, $request, $user, $locale),
                'comment' => is_string($comment) ? $comment : '',
            ]),
            url: $url,
            // Returned twice is two messages; the instance moves on between them.
            sourceKey: $instance->ulid.':'.$instance->status->value.':'.($instance->updated_at?->getTimestamp() ?? 0),
        ));
    }
}
