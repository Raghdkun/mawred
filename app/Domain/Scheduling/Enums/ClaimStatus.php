<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

/** A claim on an open shift (D73): the roster manager accepts one; the rest are declined. */
enum ClaimStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
