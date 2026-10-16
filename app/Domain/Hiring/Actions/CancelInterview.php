<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Actions\CancelEvent;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Calls an interview off: the calendar event is cancelled for the panel and the candidate is told. */
final readonly class CancelInterview
{
    public function __construct(private CancelEvent $cancelEvent, private SendCandidateMessage $messages) {}

    public function handle(Actor $actor, Interview $interview, bool $tellCandidate = true): Interview
    {
        if (! in_array($interview->status, [InterviewStatus::AwaitingBooking, InterviewStatus::Scheduled], true)) {
            throw new DomainRuleViolation('hiring.interview_closed', __('This interview is already over or cancelled.'));
        }

        $event = $interview->event()->first();
        $organizer = $event instanceof CalendarEvent ? User::query()->find($event->organizer_user_id) : null;

        if ($event instanceof CalendarEvent && $organizer instanceof User) {
            $this->cancelEvent->handle(Actor::user($organizer), $event);
        }

        $wasScheduled = $interview->status === InterviewStatus::Scheduled;
        $interview->forceFill(['status' => InterviewStatus::Cancelled])->save();

        /** @var JobApplication $application */
        $application = $interview->application()->with('candidate')->firstOrFail();

        ApplicationEvent::query()->create([
            'job_application_id' => $application->id,
            'kind' => ApplicationEventKind::Interview,
            'actor_user_id' => $actor->user?->id,
            'data' => ['interview' => $interview->ulid, 'title' => $interview->title, 'event' => 'cancelled'],
            'created_at' => now(),
        ]);

        $candidate = $application->candidate;

        if ($tellCandidate && $wasScheduled && $candidate !== null && ($candidate->email !== null || $candidate->phone !== null)) {
            $locale = $candidate->locale === 'ar' ? 'ar' : 'en';
            $this->messages->handle(
                $actor,
                $candidate,
                $application,
                $candidate->email !== null ? MessageChannel::Email : MessageChannel::WhatsApp,
                __('Your interview has been cancelled', [], $locale),
                __("Hello :name,\n\nWe have had to cancel your interview. We will be in touch about a new time.", ['name' => $candidate->name], $locale),
                MessagePurpose::Interview,
            );
        }

        activity('hiring')->causedBy($actor->user)->performedOn($interview)->event('cancelled')->log('Interview cancelled');

        return $interview;
    }
}
