<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/** Overtime counts for payroll only once approved, unless the rule needs no approval (D69). */
enum OvertimeStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Automatic = 'auto';
}
