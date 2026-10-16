<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/**
 * A requisition waits for its approval chain (D84), then is open for hiring. HR may pause it,
 * mark it filled or close it; a rejected or withdrawn request ends it.
 */
enum RequisitionStatus: string
{
    case Pending = 'pending';
    case Open = 'open';
    case OnHold = 'on_hold';
    case Filled = 'filled';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Whether candidates can be moved and postings published. */
    public function isActive(): bool
    {
        return $this === self::Open;
    }

    /**
     * @return list<self> where HR may move a requisition from here
     */
    public function next(): array
    {
        return match ($this) {
            self::Open => [self::OnHold, self::Filled, self::Closed],
            self::OnHold => [self::Open, self::Closed],
            self::Filled, self::Closed => [self::Open],
            default => [],
        };
    }
}
