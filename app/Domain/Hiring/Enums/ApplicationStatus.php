<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/** Rejected and withdrawn applications leave the board; hired ones sit in the last stage. */
enum ApplicationStatus: string
{
    case Active = 'active';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Hired = 'hired';
}
