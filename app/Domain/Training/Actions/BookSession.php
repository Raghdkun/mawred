<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Enums\SessionStatus;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\SessionCalendar;
use App\Domain\Training\Support\TrainingNotifier;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Books a seat at a session for someone on the course, or puts them on the waitlist when it's
 * full (D107). Sessions are alternatives (D108), so booking another one gives up the first.
 */
final readonly class BookSession
{
    public function __construct(
        private ConnectionInterface $db,
        private SessionCalendar $calendar,
        private TrainingNotifier $notifier,
        private CancelBooking $cancel,
    ) {}

    public function handle(Actor $actor, Enrollment $enrollment, TrainingSession $session): SessionBooking
    {
        if ($session->course_id !== $enrollment->course_id || $enrollment->status !== EnrollmentStatus::Enrolled) {
            throw new DomainRuleViolation('training.not_enrolled', __('Enrol in this course first.'));
        }

        if ($session->status !== SessionStatus::Scheduled || $session->starts_at->isPast()) {
            throw new DomainRuleViolation('training.session_closed', __('This session is no longer open for booking.'));
        }

        foreach (SessionBooking::query()->where('enrollment_id', $enrollment->id)->where('training_session_id', '!=', $session->id)->where('status', '!=', BookingStatus::Cancelled->value)->whereNull('attended_at')->get() as $other) {
            $this->cancel->handle($actor, $other);
        }

        $booking = $this->db->transaction(function () use ($enrollment, $session): SessionBooking {
            TrainingSession::query()->whereKey($session->id)->lockForUpdate()->first();
            $booking = SessionBooking::query()->firstOrNew(['training_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

            if ($booking->exists && $booking->status !== BookingStatus::Cancelled) {
                return $booking;
            }

            $full = $session->isFull();
            $booking->forceFill([
                'status' => $full ? BookingStatus::Waitlisted : BookingStatus::Booked,
                'booked_at' => $full ? null : now(),
                'waitlisted_at' => $full ? now() : null,
            ])->save();

            return $booking;
        });

        if ($booking->wasChanged('status') || $booking->wasRecentlyCreated) {
            $userId = $enrollment->employee()->value('user_id');

            if ($booking->status === BookingStatus::Booked && is_int($userId)) {
                $this->calendar->attend($session, $userId);
            }

            $this->notifier->booking($booking, $booking->status === BookingStatus::Booked ? 'booked' : 'waitlisted');
            activity('training')->causedBy($actor->user)->performedOn($enrollment)->event($booking->status->value)
                ->withProperties(['session' => $session->ulid])->log('Session booking');
        }

        return $booking;
    }
}
