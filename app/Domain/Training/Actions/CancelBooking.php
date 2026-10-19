<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Support\SessionCalendar;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Gives up a seat (or a waitlist place); a freed seat goes to the first person waiting (D107). */
final readonly class CancelBooking
{
    public function __construct(private SessionCalendar $calendar, private PromoteWaitlist $promote) {}

    public function handle(Actor $actor, SessionBooking $booking): SessionBooking
    {
        if ($booking->attended_at !== null) {
            throw new DomainRuleViolation('training.booking_attended', __('You attended this session, so the booking stays.'));
        }

        if ($booking->status === BookingStatus::Cancelled) {
            return $booking;
        }

        $wasBooked = $booking->status === BookingStatus::Booked;
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();
        $session = $booking->session()->firstOrFail();
        $userId = $booking->enrollment()->firstOrFail()->employee()->value('user_id');

        if ($wasBooked && is_int($userId)) {
            $this->calendar->leave($session, $userId);
        }

        activity('training')->causedBy($actor->user)->performedOn($booking->enrollment()->firstOrFail())->event('booking_cancelled')
            ->withProperties(['session' => $session->ulid])->log('Session booking cancelled');

        if ($wasBooked) {
            $this->promote->handle($session);
        }

        return $booking;
    }
}
