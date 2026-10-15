<?php

declare(strict_types=1);

namespace App\Domain\Leave\Enums;

/** What a time-away request takes: leave from a balance, a full day off, or a few hours. */
enum LeaveRequestKind: string
{
    case Leave = 'leave';
    case DayOff = 'day_off';
    case Hourly = 'hourly';
}
