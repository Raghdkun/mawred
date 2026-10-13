<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

/**
 * Role-based home (handoff screens 4–6): the user's highest-ranked role picks the layout.
 */
enum HomeLayout: string
{
    case Employee = 'employee';
    case Manager = 'manager';
    case HrAdmin = 'hr_admin';
}
