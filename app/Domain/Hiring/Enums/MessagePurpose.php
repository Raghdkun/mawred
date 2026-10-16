<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/** Why a candidate message went out (or `reply` when it came in). */
enum MessagePurpose: string
{
    case Manual = 'manual';
    case Stage = 'stage';
    case Rejection = 'rejection';
    case Booking = 'booking';
    case Interview = 'interview';
    case Offer = 'offer';
    case Reply = 'reply';
}
