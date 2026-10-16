<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Enums;

/** A meeting someone set up, a slot booked from a booking page (D77), or a candidate interview (D89). */
enum EventKind: string
{
    case Meeting = 'meeting';
    case Booking = 'booking';
    case Interview = 'interview';
}
