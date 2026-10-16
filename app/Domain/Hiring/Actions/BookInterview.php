<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Enums\EventKind;
use App\Domain\Calendar\Enums\ParticipantRole;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\CalendarNotifier;
use App\Domain\Calendar\Support\EventIcs;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\InterviewMode;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\PanelSlots;
use App\Domain\Hiring\Support\Texts;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Puts an interview on the calendar (D89): the panel is invited like any meeting, the candidate
 * gets the time and an `.ics` by email or WhatsApp. When the candidate picks the time, the slot
 * is checked again because the panel's calendars may have filled since the page loaded.
 */
final readonly class BookInterview
{
    public function __construct(
        private ConnectionInterface $db,
        private PanelSlots $slots,
        private CalendarNotifier $notifier,
        private EventIcs $ics,
        private SendCandidateMessage $messages,
    ) {}

    public function handle(Actor $actor, Interview $interview, CarbonImmutable $start, bool $checkFree = true): Interview
    {
        if ($interview->status !== InterviewStatus::AwaitingBooking) {
            throw new DomainRuleViolation('hiring.interview_booked', __('This interview already has a time.'));
        }

        if ($checkFree && ! $this->slots->stillFree($interview, $start->getTimestamp(), CarbonImmutable::now())) {
            throw new DomainRuleViolation('hiring.slot_taken', __('That time is no longer free. Pick another one.'));
        }

        /** @var JobApplication $application */
        $application = $interview->application()->with(['candidate', 'requisition'])->firstOrFail();
        $candidate = $application->candidate;
        $organizer = $interview->created_by_user_id ?? $actor->user?->id;
        $panel = $interview->panel()->get()->map(fn (User $u): int => $u->id)->all();

        if ($organizer === null || $candidate === null) {
            throw new DomainRuleViolation('hiring.no_organizer', __('This interview has no one to organise it.'));
        }

        $event = $this->db->transaction(function () use ($interview, $application, $candidate, $organizer, $panel, $start, $actor): CalendarEvent {
            Interview::query()->whereKey($interview->id)->lockForUpdate()->first();
            $job = Texts::one($application->requisition, 'title') ?? '';
            $end = $start->addMinutes($interview->duration_minutes);

            $event = CalendarEvent::query()->create([
                'kind' => EventKind::Interview,
                'title' => mb_substr("{$interview->title} · {$candidate->name}", 0, 200),
                'description' => trim($job."\n".route('hiring.candidates.show', [$candidate, 'application' => $application->ulid])),
                'location' => $interview->location,
                'organizer_user_id' => $organizer,
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'timezone' => $interview->timezone,
            ]);

            EventParticipant::query()->create(['calendar_event_id' => $event->id, 'user_id' => $organizer, 'role' => ParticipantRole::Organizer, 'response' => Response::Accepted, 'responded_at' => now()]);

            foreach (array_diff($panel, [$organizer]) as $userId) {
                EventParticipant::query()->create(['calendar_event_id' => $event->id, 'user_id' => $userId, 'role' => ParticipantRole::Required, 'response' => Response::Pending]);
            }

            $interview->forceFill([
                'status' => InterviewStatus::Scheduled,
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'calendar_event_id' => $event->id,
            ])->save();

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Interview,
                'actor_user_id' => $actor->user?->id,
                'data' => ['interview' => $interview->ulid, 'title' => $interview->title, 'event' => 'booked', 'at' => $start->toIso8601String()],
                'created_at' => now(),
            ]);
            $application->forceFill(['last_activity_at' => now()])->save();

            return $event;
        });

        $event->load(['participants', 'organizer']);

        foreach ($event->participants as $participant) {
            if ($participant->role !== ParticipantRole::Organizer || $participant->user_id !== $actor->user?->id) {
                $this->notifier->tell($event, $participant, 'invited');
            }
        }

        $this->confirm($actor, $interview, $application, $event);

        activity('hiring')->causedBy($actor->user)->performedOn($interview)->event('booked')
            ->withProperties(['at' => $start->toIso8601String()])->log('Interview booked');

        return $interview->refresh();
    }

    private function confirm(Actor $actor, Interview $interview, JobApplication $application, CalendarEvent $event): void
    {
        $candidate = $application->candidate;

        if ($candidate === null || ($candidate->email === null && $candidate->phone === null)) {
            return;
        }

        $locale = $candidate->locale === 'ar' ? 'ar' : 'en';
        $when = CarbonImmutable::parse($event->starts_at)->setTimezone($interview->timezone)->settings(['locale' => $locale])->isoFormat('LLLL');
        $where = match ($interview->mode) {
            InterviewMode::Video => __('by video call; we will send the link before the interview', [], $locale),
            InterviewMode::Phone => __('by phone; we will call you', [], $locale),
            InterviewMode::Onsite => $interview->location ?? (Texts::one($application->requisition?->branch()->first(), 'name', $locale) ?? ''),
        };

        $this->messages->handle(
            $actor,
            $candidate,
            $application,
            $candidate->email !== null ? MessageChannel::Email : MessageChannel::WhatsApp,
            __('Your interview is booked', [], $locale),
            __("Hello :name,\n\nYour interview is on :when (:zone), :where. We look forward to meeting you.", ['name' => $candidate->name, 'when' => $when, 'zone' => $interview->timezone, 'where' => $where], $locale),
            MessagePurpose::Interview,
            ics: $this->ics->for($event),
        );
    }
}
