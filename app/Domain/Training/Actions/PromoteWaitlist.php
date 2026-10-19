<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Enums\SessionStatus;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\SessionCalendar;
use App\Domain\Training\Support\TrainingNotifier;
use Illuminate\Database\ConnectionInterface;

/**
 * Fills free seats from the waitlist, first in line first (D107): they're booked, invited to the
 * calendar event and told. Runs when a seat frees up or a session gets more seats.
 */
final readonly class PromoteWaitlist
{
    public function __construct(
        private ConnectionInterface $db,
        private SessionCalendar $calendar,
        private TrainingNotifier $notifier,
    ) {}

    /**
     * @return list<SessionBooking>
     */
    public function handle(TrainingSession $session): array
    {
        if ($session->status !== SessionStatus::Scheduled || $session->starts_at->isPast()) {
            return [];
        }

        $promoted = $this->db->transaction(function () use ($session): array {
            TrainingSession::query()->whereKey($session->id)->lockForUpdate()->first();
            $free = $session->capacity === null ? PHP_INT_MAX : $session->capacity - $session->seatsTaken();
            $promoted = [];

            if ($free <= 0) {
                return [];
            }

            foreach ($session->bookings()->where('status', BookingStatus::Waitlisted->value)->oldest('waitlisted_at')->orderBy('id')->limit(min($free, 1000))->get() as $booking) {
                $booking->forceFill(['status' => BookingStatus::Booked, 'booked_at' => now()])->save();
                $promoted[] = $booking;
            }

            return $promoted;
        });

        foreach ($promoted as $booking) {
            $userId = $booking->enrollment()->firstOrFail()->employee()->value('user_id');

            if (is_int($userId)) {
                $this->calendar->attend($session, $userId);
            }

            $this->notifier->booking($booking, 'promoted');
        }

        return $promoted;
    }
}
