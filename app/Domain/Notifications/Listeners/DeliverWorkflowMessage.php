<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\Notifications\Support\WorkflowSubject;
use App\Domain\Workflows\Events\WorkflowNotificationRequested;
use App\Support\Templates\SafeTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Delivers a Notify node's message. Its text was written in the workflow builder and may use
 * `{{ request.number }}`, `{{ request.type }}` and `{{ requester.name }}`.
 */
final readonly class DeliverWorkflowMessage implements ShouldQueue
{
    public const array VARIABLES = ['request.number', 'request.type', 'requester.name', 'link'];

    public function __construct(
        private NotificationRouter $router,
        private WorkflowSubject $subjects,
        private SafeTemplate $templates,
    ) {}

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function handle(WorkflowNotificationRequested $event): void
    {
        $instance = $event->step->instance()->firstOrFail();
        $request = $this->subjects->request($instance);
        $url = $this->subjects->url($request);

        foreach (User::query()->whereIn('id', $event->userIds)->get() as $user) {
            $this->router->send($user, new NotificationMessage(
                event: 'workflow.message',
                category: NotificationCategory::Requests,
                content: function (string $channel, string $locale) use ($event, $instance, $request, $user): array {
                    $vars = $this->subjects->variables($instance, $request, $user, $locale);
                    $variables = [
                        'request' => ['number' => $vars['request_number'], 'type' => $vars['request_type']],
                        'requester' => ['name' => $vars['requester']],
                        'link' => $vars['link'],
                    ];
                    $subject = $event->subject[$locale] ?? $event->subject['en'] ?? '';
                    $body = $event->body[$locale] ?? $event->body['en'] ?? '';

                    return [
                        'subject' => $this->templates->render($subject !== '' ? $subject : $body, $variables, $locale, escapeHtml: false),
                        'body' => $subject !== '' ? $this->templates->render($body, $variables, $locale, escapeHtml: false) : '',
                    ];
                },
                url: $url,
                sourceKey: $event->step->ulid,
                channels: [$event->channel],
            ));
        }
    }
}
