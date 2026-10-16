<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Models\CalendarEvent;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Meetings are seen by the people on them; only the organiser changes or cancels them. Others
 * only ever see untitled busy time (D76), never the event itself.
 */
final class CalendarEventPolicy
{
    public function view(User $user, CalendarEvent $event): bool
    {
        return $event->participants()->where('user_id', $user->id)->exists()
            || ($event->series_id !== null && CalendarEvent::query()->whereKey($event->series_id)->whereHas('participants', fn (Builder $q) => $q->where('user_id', $user->id))->exists());
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $event->organizer_user_id === $user->id;
    }
}
