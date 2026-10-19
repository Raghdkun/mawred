<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

/** A seat at a session: booked, waiting for one (D107), or given up. */
enum BookingStatus: string
{
    case Booked = 'booked';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
}
