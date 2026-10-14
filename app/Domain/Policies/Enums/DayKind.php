<?php

declare(strict_types=1);

namespace App\Domain\Policies\Enums;

/** What kind of day work happened on, for overtime multipliers. */
enum DayKind: string
{
    case Workday = 'workday';
    case Weekend = 'weekend';
    case Holiday = 'holiday';
}
