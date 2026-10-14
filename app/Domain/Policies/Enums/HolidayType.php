<?php

declare(strict_types=1);

namespace App\Domain\Policies\Enums;

/**
 * Holiday rule types (D12). No Hijri-recurring type: Islamic holidays are one-off entries.
 */
enum HolidayType: string
{
    case Fixed = 'fixed';
    case NthWeekday = 'nth_weekday';
    case OneOff = 'one_off';
}
