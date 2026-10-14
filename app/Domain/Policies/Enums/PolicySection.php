<?php

declare(strict_types=1);

namespace App\Domain\Policies\Enums;

/**
 * Editor sections (handoff 17), in display order.
 */
enum PolicySection: string
{
    case Leave = 'leave';
    case WorkWeek = 'work_week';
    case Overtime = 'overtime';
    case Attendance = 'attendance';
    case Rest = 'rest';
    case Employment = 'employment';
}
