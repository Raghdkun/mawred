<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
