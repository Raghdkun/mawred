<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

/**
 * An enrolment is requested (waiting for approval, D99) or enrolled, then completed. Rejected
 * and cancelled ones end it; a new one can be made.
 */
enum EnrollmentStatus: string
{
    case Requested = 'requested';
    case Enrolled = 'enrolled';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Still in play: only one live enrolment per person and course. */
    public function isLive(): bool
    {
        return $this === self::Requested || $this === self::Enrolled;
    }
}
