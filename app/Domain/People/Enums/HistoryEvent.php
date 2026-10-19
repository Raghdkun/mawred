<?php

declare(strict_types=1);

namespace App\Domain\People\Enums;

enum HistoryEvent: string
{
    case Hired = 'hired';
    case PlacementChanged = 'placement_changed';
    case SalaryChanged = 'salary_changed';
    case AllowanceChanged = 'allowance_changed';
    case StatusChanged = 'status_changed';
    case Terminated = 'terminated';
}
