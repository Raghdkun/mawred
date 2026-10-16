<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Enums\EventKind;
use App\Domain\Calendar\Enums\ParticipantRole;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Models\BookingPage;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\BookingSlots;
use App\Domain\Calendar\Support\CalendarNotifier;
use App\Domain\Forms\Support\FieldSpec;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Books a slot from a booking page (D77): it must still be open (the page is locked while
 * checking, so two people can't take the same slot). Both people get it on their calendar; the
 * owner is told.
 */
final readonly class BookSlot
{
    public function __construct(private ConnectionInterface $db, private CalendarNotifier $notifier) {}

    public function handle(Actor $actor, BookingPage $page, CarbonImmutable $start, ?string $note = null): CalendarEvent
    {
        $user = $actor->user;

        if (! $user instanceof User || ! BookingSlots::canBook($page, $user)) {
            throw new DomainRuleViolation('calendar.cannot_book', __('This booking page isn’t open to you.'));
        }

        $event = $this->db->transaction(function () use ($page, $start, $user, $note): CalendarEvent {
            BookingPage::query()->whereKey($page->id)->lockForUpdate()->first();
            $slots = BookingSlots::between($page, $start->startOfDay(), $start->startOfDay()->addDays(2), CarbonImmutable::now());
            $match = array_values(array_filter($slots, fn (array $s): bool => $s[0] === $start->getTimestamp()));

            if ($match === []) {
                throw new DomainRuleViolation('calendar.slot_taken', __('That time is no longer free. Pick another one.'));
            }

            $event = CalendarEvent::query()->create([
                'kind' => EventKind::Booking,
                'title' => FieldSpec::string($page->getTranslation('title', app()->getLocale())).' · '.$user->name,
                'description' => $note,
                'location' => $page->location,
                'organizer_user_id' => $page->owner_user_id,
                'starts_at' => CarbonImmutable::createFromTimestampUTC($match[0][0]),
                'ends_at' => CarbonImmutable::createFromTimestampUTC($match[0][1]),
                'timezone' => $page->timezone,
                'booking_page_id' => $page->id,
            ]);

            foreach ([[$page->owner_user_id, ParticipantRole::Organizer], [$user->id, ParticipantRole::Required]] as [$id, $role]) {
                EventParticipant::query()->create(['calendar_event_id' => $event->id, 'user_id' => $id, 'role' => $role, 'response' => Response::Accepted, 'responded_at' => now()]);
            }

            return $event;
        });

        $event->load(['participants', 'organizer']);
        $owner = $event->participants->firstWhere('user_id', $page->owner_user_id);

        if ($owner instanceof EventParticipant) {
            $this->notifier->tell($event, $owner, 'booked');
        }

        return $event;
    }
}
