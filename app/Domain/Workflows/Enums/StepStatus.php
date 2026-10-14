<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** One node activation's state. */
enum StepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
