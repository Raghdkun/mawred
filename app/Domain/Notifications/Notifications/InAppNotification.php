<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The bell entry (handoff screen 44): stored, then pushed over Reverb to the person's private
 * channel. Text is already rendered in the recipient's locale.
 */
final class InAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $category,
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{event: string, category: string, title: string, body: string|null, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'category' => $this->category, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onQueue('notifications');
    }

    public function databaseType(object $notifiable): string
    {
        return $this->event;
    }
}
