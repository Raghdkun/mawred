<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Calendar\Enums\EventKind;
use App\Domain\Calendar\Enums\ParticipantRole;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\CalendarNotifier;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Enums\SessionMode;
use App\Domain\Training\Models\TrainingSession;

/**
 * Keeps a session's calendar event in step (D98, D101): the trainer organises it, everyone with
 * a booked seat attends, and changes or cancellations go out with an `.ics` like any meeting.
 */
final readonly class SessionCalendar
{
    public function __construct(private CalendarNotifier $notifier) {}

    public function sync(TrainingSession $session): ?CalendarEvent
    {
        if ($session->trainer_user_id === null) {
            return null;
        }

        $course = $session->course()->firstOrFail();
        $values = [
            'kind' => EventKind::Training,
            'title' => mb_substr(FieldSpec::text($course->getTranslations('title'), 'en').' · '.$course->code, 0, 200),
            'description' => trim(($session->mode === SessionMode::Online ? ($session->meeting_url ?? '')."\n" : '').route('training.courses.show', $course)),
            'location' => $session->mode === SessionMode::Online ? $session->meeting_url : $session->location,
            'organizer_user_id' => $session->trainer_user_id,
            'starts_at' => $session->starts_at->utc(),
            'ends_at' => $session->ends_at->utc(),
            'timezone' => $session->timezone,
        ];
        $event = $session->calendar_event_id === null ? null : CalendarEvent::query()->find($session->calendar_event_id);

        if (! $event instanceof CalendarEvent) {
            $event = CalendarEvent::query()->create($values);
            EventParticipant::query()->create(['calendar_event_id' => $event->id, 'user_id' => $session->trainer_user_id, 'role' => ParticipantRole::Organizer, 'response' => Response::Accepted, 'responded_at' => now()]);
            $session->forceFill(['calendar_event_id' => $event->id])->save();

            return $event;
        }

        $event->fill($values);

        if ($event->isDirty(['starts_at', 'ends_at', 'location', 'organizer_user_id'])) {
            $event->forceFill(['sequence' => $event->sequence + 1])->save();

            foreach ($event->participants()->get() as $participant) {
                if ($participant->role !== ParticipantRole::Organizer) {
                    $this->notifier->tell($event, $participant, 'updated');
                }
            }
        } else {
            $event->save();
        }

        return $event;
    }

    public function attend(TrainingSession $session, int $userId): void
    {
        $event = $this->sync($session);

        if (! $event instanceof CalendarEvent || EventParticipant::query()->where('calendar_event_id', $event->id)->where('user_id', $userId)->exists()) {
            return;
        }

        $participant = EventParticipant::query()->create(['calendar_event_id' => $event->id, 'user_id' => $userId, 'role' => ParticipantRole::Required, 'response' => Response::Accepted, 'responded_at' => now()]);
        $this->notifier->tell($event, $participant, 'invited');
    }

    public function leave(TrainingSession $session, int $userId): void
    {
        $event = $session->calendar_event_id === null ? null : CalendarEvent::query()->find($session->calendar_event_id);
        $participant = $event instanceof CalendarEvent ? EventParticipant::query()->where('calendar_event_id', $event->id)->where('user_id', $userId)->where('role', '!=', ParticipantRole::Organizer->value)->first() : null;

        if ($event instanceof CalendarEvent && $participant instanceof EventParticipant) {
            $this->notifier->tell($event, $participant, 'cancelled');
            $participant->delete();
        }
    }

    public function cancel(TrainingSession $session): void
    {
        $event = $session->calendar_event_id === null ? null : CalendarEvent::query()->find($session->calendar_event_id);

        if (! $event instanceof CalendarEvent || $event->cancelled_at !== null) {
            return;
        }

        $event->forceFill(['cancelled_at' => now(), 'sequence' => $event->sequence + 1])->save();

        foreach ($event->participants()->get() as $participant) {
            if ($participant->role !== ParticipantRole::Organizer) {
                $this->notifier->tell($event, $participant, 'cancelled');
            }
        }
    }
}
