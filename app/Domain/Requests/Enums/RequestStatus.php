<?php

declare(strict_types=1);

namespace App\Domain\Requests\Enums;

/** A request's status, kept in step with its workflow instance. */
enum RequestStatus: string
{
    case InReview = 'in_review';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::InReview || $this === self::Returned;
    }
}
