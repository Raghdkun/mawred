<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Enums\SessionStatus;
use App\Domain\Training\Jobs\RefreshCourseProgressJob;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\SessionCalendar;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Cancels a session: every seat and waitlist place is released and the calendar event cancelled. */
final readonly class CancelSession
{
    public function __construct(private SessionCalendar $calendar) {}

    public function handle(Actor $actor, TrainingSession $session, ?string $reason = null): TrainingSession
    {
        if ($session->status === SessionStatus::Cancelled) {
            return $session;
        }

        if ($session->bookings()->whereNotNull('attended_at')->exists()) {
            throw new DomainRuleViolation('training.session_attended', __('People already attended this session, so it can’t be cancelled.'));
        }

        $session->forceFill(['status' => SessionStatus::Cancelled])->save();
        $session->bookings()->where('status', '!=', BookingStatus::Cancelled->value)->update(['status' => BookingStatus::Cancelled->value]);
        $this->calendar->cancel($session);
        dispatch(new RefreshCourseProgressJob($session->course_id));

        activity('training')->causedBy($actor->user)->performedOn($session)->event('cancelled')->withProperties(['reason' => $reason])->log('Session cancelled');

        return $session;
    }
}
