<?php

declare(strict_types=1);

namespace App\Domain\Leave\Enums;

/** Pending requests hold their days (D64); approval turns the hold into a debit. */
enum LeaveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Pending and approved time away blocks overlapping requests and shows on the calendar. */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
