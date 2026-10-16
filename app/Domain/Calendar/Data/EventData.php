<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Data;

use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\Occurrence;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One occurrence on someone's calendar, with the people on it and the viewer's reply. */
#[TypeScript]
final class EventData extends Data
{
    /**
     * @param  list<array{id: string, name: string, role: string, response: string}>  $participants
     * @param  array{freq: string, interval?: int, byweekday?: list<int>, until?: string|null, count?: int|null}|null  $recurrence
     */
    public function __construct(
        public string $id,
        public string $key,
        public string $kind,
        public string $title,
        public ?string $description,
        public ?string $location,
        public string $startsAt,
        public string $endsAt,
        public string $timezone,
        public bool $private,
        public ?array $recurrence,
        public bool $repeats,
        public string $organizer,
        public bool $isOrganizer,
        public ?string $myResponse,
        public array $participants,
    ) {}

    /**
     * Expects `participants.user` and `organizer` loaded.
     */
    public static function fromOccurrence(Occurrence $occurrence, int $viewerId): self
    {
        $event = $occurrence->event;
        $series = $event->series_id === null ? $event : null;

        return new self(
            id: $event->ulid,
            key: $occurrence->key(),
            kind: $event->kind->value,
            title: $event->title,
            description: $event->description,
            location: $event->location,
            startsAt: $occurrence->start->toIso8601String(),
            endsAt: $occurrence->end->toIso8601String(),
            timezone: $event->timezone,
            private: $event->visibility->value === 'private',
            recurrence: $series?->recurrence,
            repeats: $event->recurrence !== null || $event->series_id !== null,
            organizer: $event->organizer->name ?? '',
            isOrganizer: $event->organizer_user_id === $viewerId,
            myResponse: $event->participants->firstWhere('user_id', $viewerId)?->response->value,
            participants: array_values($event->participants->map(fn (EventParticipant $p): array => [
                'id' => $p->user->ulid ?? '',
                'name' => $p->user->name ?? '',
                'role' => $p->role->value,
                'response' => $p->response->value,
            ])->all()),
        );
    }
}
