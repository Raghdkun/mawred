<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** Lifecycle of a workflow instance. Returned = sent back to the requester for changes; Attention = a step failed and needs an admin (dead-letter view). */
enum InstanceStatus: string
{
    case Running = 'running';
    case Returned = 'returned';
    case Attention = 'attention';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
