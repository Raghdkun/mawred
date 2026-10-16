<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum RejectionReason: string
{
    case NotQualified = 'not_qualified';
    case Knockout = 'knockout';
    case BetterCandidate = 'better_candidate';
    case Salary = 'salary';
    case NoShow = 'no_show';
    case Withdrew = 'withdrew';
    case PositionClosed = 'position_closed';
    case Other = 'other';
}
