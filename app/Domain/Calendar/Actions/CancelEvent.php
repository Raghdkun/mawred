<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Actions;

use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Support\CalendarNotifier;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Calls off a meeting: one occurrence (skipped), that occurrence and the ones after (the series
 * ends the day before), or all of it. Everyone on it is told.
 */
final readonly class CancelEvent
{
    public function __construct(private ConnectionInterface $db, private CalendarNotifier $notifier) {}

    /**
     * @param  'all'|'this'|'following'  $scope
     */
    public function handle(Actor $actor, CalendarEvent $event, string $scope = 'all', ?CarbonImmutable $occurrence = null): void
    {
        if ($event->organizer_user_id !== $actor->user?->id) {
            throw new DomainRuleViolation('calendar.not_organizer', __('Only the organiser can change this meeting.'));
        }

        $series = $event->series_id === null ? $event : CalendarEvent::query()->find($event->series_id);
        $occurrence = $event->series_id !== null ? $event->original_start : $occurrence;

        $this->db->transaction(function () use ($event, $series, $scope, $occurrence): void {
            if ($scope === 'this' && $series instanceof CalendarEvent && $occurrence instanceof CarbonImmutable && $series->recurrence !== null) {
                $series->forceFill(['exdates' => array_values(array_unique([...$series->exdates ?? [], $occurrence->utc()->toIso8601ZuluString()])), 'sequence' => $series->sequence + 1])->save();

                if ($event->series_id !== null) {
                    $event->forceFill(['cancelled_at' => now()])->save();
                }

                return;
            }

            if ($scope === 'following' && $series instanceof CalendarEvent && $occurrence instanceof CarbonImmutable && $series->recurrence !== null) {
                $series->forceFill(['recurrence' => [...$series->recurrence, 'until' => $occurrence->setTimezone($series->timezone)->subDay()->toDateString(), 'count' => null], 'sequence' => $series->sequence + 1])->save();

                return;
            }

            $target = $series ?? $event;
            $target->forceFill(['cancelled_at' => now(), 'sequence' => $target->sequence + 1])->save();
            CalendarEvent::query()->where('series_id', $target->id)->update(['cancelled_at' => now()]);
        });

        $notify = $series ?? $event;
        $notify->load(['participants', 'organizer']);

        foreach ($notify->participants as $participant) {
            if ($participant->user_id !== $notify->organizer_user_id) {
                $this->notifier->tell($scope === 'this' && $event->series_id !== null ? $event : $notify, $participant, 'cancelled');
            }
        }

        activity('calendar')->causedBy($actor->user)->performedOn($notify)->event('cancelled')->withProperties(['scope' => $scope])->log('Meeting cancelled');
    }
}
