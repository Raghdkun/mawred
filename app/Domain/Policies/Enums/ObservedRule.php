<?php

declare(strict_types=1);

namespace App\Domain\Policies\Enums;

/**
 * What happens when a holiday falls on a weekend day.
 */
enum ObservedRule: string
{
    case None = 'none';
    /** US federal style: Saturday → Friday, Sunday → Monday. */
    case NearestWeekday = 'nearest_weekday';
    /** Move to the next working day of the branch. */
    case NextWorkingDay = 'next_working_day';
    /** Move to the previous working day of the branch. */
    case PreviousWorkingDay = 'previous_working_day';
}
