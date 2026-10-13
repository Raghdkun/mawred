<?php

declare(strict_types=1);

namespace App\Domain\People\Enums;

/**
 * Employment status. Probation is a date (probation_ends_on), not a status; leave is derived from
 * the Leave module later.
 */
enum EmployeeStatus: string
{
    /** Hired with a future start date. */
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function isEmployed(): bool
    {
        return $this !== self::Terminated;
    }
}
