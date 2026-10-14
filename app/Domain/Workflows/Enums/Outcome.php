<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** How an instance ended (set by the End node or a cancellation). */
enum Outcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
