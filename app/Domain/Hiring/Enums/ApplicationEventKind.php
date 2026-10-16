<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/** One line of an application's history (stage moves feed time-in-stage and funnel reports). */
enum ApplicationEventKind: string
{
    case Applied = 'applied';
    case Moved = 'moved';
    case Rejected = 'rejected';
    case Reopened = 'reopened';
    case Note = 'note';
    case Merged = 'merged';
}
