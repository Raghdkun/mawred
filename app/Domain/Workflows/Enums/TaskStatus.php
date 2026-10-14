<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** A task's state. */
enum TaskStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
