<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Models\SessionBooking;
use App\Support\Actors\Actor;

/** The trainer ticks someone off the roster, or untick a mistake (D101). */
final readonly class MarkAttendance
{
    public function __construct(private RefreshEnrollmentProgress $refresh) {}

    public function handle(Actor $actor, SessionBooking $booking, bool $attended): SessionBooking
    {
        $booking->forceFill($attended
            ? ['status' => BookingStatus::Booked, 'attended_at' => $booking->attended_at ?? now(), 'check_in' => $booking->check_in ?? 'manual', 'marked_by' => $actor->user?->id]
            : ['attended_at' => null, 'check_in' => null, 'marked_by' => $actor->user?->id])->save();

        activity('training')->causedBy($actor->user)->performedOn($booking->enrollment()->firstOrFail())->event($attended ? 'attended' : 'absent')
            ->withProperties(['session' => $booking->session()->value('ulid')])->log('Attendance marked');

        $this->refresh->handle($booking->enrollment()->firstOrFail());

        return $booking;
    }
}
