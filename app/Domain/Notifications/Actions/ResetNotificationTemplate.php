<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Models\NotificationTemplate;

/** Goes back to Mawred's built-in wording for one event and channel. */
final readonly class ResetNotificationTemplate
{
    public function handle(User $actor, string $event, string $channel): void
    {
        $deleted = NotificationTemplate::query()->where(['event' => $event, 'channel' => $channel])->get();

        foreach ($deleted as $template) {
            $template->delete();
        }

        activity('notifications')->causedBy($actor)->event('template.reset')->withProperties(['event' => $event, 'channel' => $channel])->log('Notification template reset');
    }
}
