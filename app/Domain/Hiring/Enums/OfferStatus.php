<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/**
 * An offer is drafted, approved through its workflow (D91), sent, then accepted or declined by
 * the candidate (D83). Rejected, withdrawn and expired offers end it; a new one can be drafted.
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    /** Still in play: a new offer can't be drafted beside it. */
    public function isLive(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved, self::Sent, self::Accepted], true);
    }
}
