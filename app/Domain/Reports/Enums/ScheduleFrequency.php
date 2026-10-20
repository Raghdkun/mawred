<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

/** How often a scheduled report runs (D120). */
enum ScheduleFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
