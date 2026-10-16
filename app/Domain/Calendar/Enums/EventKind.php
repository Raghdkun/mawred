<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Enums;

/** A meeting someone set up, or a slot booked from a booking page (D77). Interviews join in Phase 6. */
enum EventKind: string
{
    case Meeting = 'meeting';
    case Booking = 'booking';
}
