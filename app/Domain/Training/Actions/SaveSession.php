<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Training\Data\SessionInputData;
use App\Domain\Training\Enums\SessionMode;
use App\Domain\Training\Enums\SessionStatus;
use App\Domain\Training\Jobs\RefreshCourseProgressJob;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\SessionCalendar;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Plans or changes a session (D98): time, place, seats and trainer, with its calendar event.
 * More seats move people up from the waitlist (D107); fewer can't drop below those booked.
 */
final readonly class SaveSession
{
    public function __construct(
        private ConnectionInterface $db,
        private SessionCalendar $calendar,
        private PromoteWaitlist $promote,
    ) {}

    public function handle(Actor $actor, Course $course, SessionInputData $input, ?TrainingSession $session = null): TrainingSession
    {
        if ($session instanceof TrainingSession && $session->status === SessionStatus::Cancelled) {
            throw new DomainRuleViolation('training.session_cancelled', __('This session was cancelled.'));
        }

        if ($input->mode === SessionMode::Online->value && ($input->meetingUrl ?? '') === '') {
            $message = __('Add the meeting link for an online session.');

            throw new DomainRuleViolation('training.meeting_url', $message, ['meetingUrl' => [$message]]);
        }

        if ($session instanceof TrainingSession && $input->capacity !== null && $input->capacity < $session->seatsTaken()) {
            $message = __('More people are booked than that. Cancel some seats first.');

            throw new DomainRuleViolation('training.capacity_below_booked', $message, ['capacity' => [$message]]);
        }

        $trainer = $input->trainerId === null ? null : User::query()->where('ulid', $input->trainerId)->first();

        $saved = $this->db->transaction(function () use ($actor, $course, $input, $session, $trainer): TrainingSession {
            $session ??= new TrainingSession(['course_id' => $course->id, 'checkin_secret' => Str::random(40)]);
            $session->fill([
                'starts_at' => CarbonImmutable::parse($input->startsAt)->utc(),
                'ends_at' => CarbonImmutable::parse($input->endsAt)->utc(),
                'timezone' => $input->timezone,
                'mode' => SessionMode::from($input->mode),
                'location' => $input->mode === SessionMode::Onsite->value ? $input->location : null,
                'meeting_url' => $input->mode === SessionMode::Online->value ? $input->meetingUrl : null,
                'capacity' => $input->capacity,
                'trainer_user_id' => $trainer->id ?? $session->trainer_user_id ?? $actor->user?->id,
            ])->save();

            $this->calendar->sync($session);

            if ($session->wasRecentlyCreated) {
                dispatch(new RefreshCourseProgressJob($course->id))->afterCommit();
            }

            activity('training')->causedBy($actor->user)->performedOn($session)->event($session->wasRecentlyCreated ? 'created' : 'updated')
                ->withProperties(['course' => $course->code])->log('Session saved');

            return $session;
        });

        $this->promote->handle($saved);

        return $saved;
    }
}
