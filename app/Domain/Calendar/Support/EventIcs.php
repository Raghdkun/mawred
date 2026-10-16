<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Forms\Support\FieldSpec;
use Carbon\CarbonImmutable;

/** The `.ics` for an event or a series ("add to my calendar", invitation attachments). */
final class EventIcs
{
    public function for(CalendarEvent $event, bool $cancelled = false): string
    {
        $event->loadMissing(['participants.user', 'organizer']);
        $host = parse_url(FieldSpec::string(config('app.url')), PHP_URL_HOST);

        return IcsWriter::write([
            'uid' => ($event->series instanceof CalendarEvent ? $event->series->ulid : $event->ulid).'@'.(is_string($host) ? $host : 'mawred'),
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'starts' => $event->starts_at,
            'ends' => $event->ends_at,
            'organizer' => ['name' => $event->organizer->name ?? '', 'email' => $event->organizer?->email],
            'attendees' => array_values($event->participants->map(fn (EventParticipant $p): array => ['name' => $p->user->name ?? '', 'email' => $p->user?->email, 'response' => $p->response->value])->all()),
            'sequence' => $event->sequence,
            'cancelled' => $cancelled || $event->cancelled_at !== null,
            'recurrence' => $event->series_id === null ? $event->recurrence : null,
            'exdates' => $event->exdates ?? [],
            'url' => route('calendar.show', $event),
            'stamp' => CarbonImmutable::now('UTC'),
        ]);
    }
}
