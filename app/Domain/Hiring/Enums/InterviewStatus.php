<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/** An interview waits for the candidate to pick a time (D89), then is on the calendar. */
enum InterviewStatus: string
{
    case AwaitingBooking = 'awaiting';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
