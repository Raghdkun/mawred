<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;

/**
 * Meeting messages (category Schedule): invitations with one-tap Going / Maybe / Not going and an
 * `.ics` attachment (D75), changes, cancellations and new bookings.
 */
final readonly class CalendarNotifier
{
    public function __construct(private NotificationRouter $router) {}

    /**
     * @param  'invited'|'updated'|'cancelled'|'booked'  $what
     */
    public function tell(CalendarEvent $event, EventParticipant $participant, string $what): void
    {
        $user = User::query()->find($participant->user_id);

        if (! $user instanceof User) {
            return;
        }

        $key = FieldSpec::string(config('app.key'));
        $replies = $what === 'invited' || $what === 'updated';
        $when = fn (string $locale): string => $event->starts_at->setTimezone($user->timezone ?? $event->timezone)->settings(['locale' => $locale])->isoFormat('LLLL');

        $this->router->send($user, new NotificationMessage(
            event: 'calendar.'.$what,
            category: NotificationCategory::Schedule,
            content: fn (string $channel, string $locale): array => [
                'subject' => FieldSpec::string(match ($what) {
                    'invited' => __('Invitation: :title', ['title' => $event->title], $locale),
                    'updated' => __('Changed: :title', ['title' => $event->title], $locale),
                    'cancelled' => __('Cancelled: :title', ['title' => $event->title], $locale),
                    'booked' => __('New booking: :title', ['title' => $event->title], $locale),
                }),
                'body' => FieldSpec::string(__(':when · :organizer', ['when' => $when($locale), 'organizer' => $event->organizer->name ?? ''], $locale)).($event->location ? "\n".$event->location : ''),
            ],
            url: route('calendar.show', $event),
            sourceKey: $event->ulid.':'.$what.':'.$event->sequence,
            actions: $replies && $participant->user_id !== $event->organizer_user_id ? fn (string $channel): array => [
                'accepted' => route('calendar-replies.show', ReplyTokens::make($participant->ulid, Response::Accepted, $key)),
                'tentative' => route('calendar-replies.show', ReplyTokens::make($participant->ulid, Response::Tentative, $key)),
                'declined' => route('calendar-replies.show', ReplyTokens::make($participant->ulid, Response::Declined, $key)),
            ] : null,
            ics: $what === 'booked' ? null : fn (): string => resolve(EventIcs::class)->for($event, $what === 'cancelled'),
        ));
    }
}
