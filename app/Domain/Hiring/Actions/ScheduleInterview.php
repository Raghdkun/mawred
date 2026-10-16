<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Data\InterviewInputData;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\InterviewMode;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\CandidateLinks;
use App\Domain\Hiring\Support\Texts;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Sets up an interview with its panel (D92): straight onto the calendar when a time is given,
 * otherwise the candidate gets a link to pick a time the whole panel is free (D89).
 */
final readonly class ScheduleInterview
{
    public function __construct(
        private ConnectionInterface $db,
        private BookInterview $book,
        private SendCandidateMessage $messages,
    ) {}

    public function handle(Actor $actor, JobApplication $application, InterviewInputData $input): Interview
    {
        if ($application->status !== ApplicationStatus::Active) {
            throw new DomainRuleViolation('hiring.not_active', __('Reopen this application before planning an interview.'));
        }

        $panel = User::query()->whereIn('ulid', $input->panel)->where('status', 'active')->get();

        if ($panel->count() !== count($input->panel)) {
            $message = __('Pick active colleagues for the panel.');

            throw new DomainRuleViolation('hiring.bad_panel', $message, ['panel' => [$message]]);
        }

        $candidate = $application->candidate()->firstOrFail();

        if ($input->startsAt === null && $candidate->email === null && $candidate->phone === null) {
            $message = __('This candidate has no email or mobile number to send a booking link to.');

            throw new DomainRuleViolation('hiring.no_contact', $message, ['startsAt' => [$message]]);
        }

        $interview = $this->db->transaction(function () use ($actor, $application, $input, $panel): Interview {
            $interview = Interview::query()->create([
                'job_application_id' => $application->id,
                'title' => $input->title,
                'duration_minutes' => $input->durationMinutes,
                'mode' => InterviewMode::from($input->mode),
                'location' => $input->location,
                'timezone' => $input->timezone,
                'status' => InterviewStatus::AwaitingBooking,
                'created_by_user_id' => $actor->user?->id,
            ]);
            $interview->panel()->sync($panel->modelKeys());

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Interview,
                'actor_user_id' => $actor->user?->id,
                'data' => ['interview' => $interview->ulid, 'title' => $input->title, 'event' => 'planned'],
                'created_at' => now(),
            ]);
            $application->forceFill(['last_activity_at' => now()])->save();

            activity('hiring')->causedBy($actor->user)->performedOn($interview)->event('planned')
                ->withProperties(['panel' => $panel->count(), 'booking' => $input->startsAt === null])->log('Interview planned');

            return $interview;
        });

        if ($input->startsAt !== null) {
            return $this->book->handle($actor, $interview, CarbonImmutable::parse($input->startsAt), checkFree: false);
        }

        $this->sendBookingLink($actor, $interview, $candidate, $application);

        return $interview;
    }

    private function sendBookingLink(Actor $actor, Interview $interview, Candidate $candidate, JobApplication $application): void
    {
        $locale = $candidate->locale === 'ar' ? 'ar' : 'en';
        $job = Texts::one($application->requisition()->first(), 'title', $locale) ?? '';
        $interview->forceFill(['booking_sent_at' => now()])->save();

        $this->messages->handle(
            $actor,
            $candidate,
            $application,
            $candidate->email !== null ? MessageChannel::Email : MessageChannel::WhatsApp,
            __('Pick a time for your interview', [], $locale),
            __("Hello :name,\n\nThank you for applying for :job. We'd like to meet you: please pick a time for a :minutes-minute interview that suits you. The link is open for :days days.", [
                'name' => $candidate->name, 'job' => $job, 'minutes' => $interview->duration_minutes, 'days' => Interview::BOOKING_DAYS,
            ], $locale),
            MessagePurpose::Booking,
            [['label' => __('Pick a time', [], $locale), 'url' => CandidateLinks::booking($interview)]],
        );
    }
}
