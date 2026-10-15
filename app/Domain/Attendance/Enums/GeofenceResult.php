<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/** Where a punch was, against the branch area (D26). */
enum GeofenceResult: string
{
    case Inside = 'inside';
    case Outside = 'outside';
    /** No location sent, or the branch has no area set. */
    case Unknown = 'unknown';
    /** Not checked: the policy is `off`, or the punch came from a kiosk or a correction. */
    case NotChecked = 'off';
}
