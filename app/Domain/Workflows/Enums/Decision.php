<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** What a person decided on a task. */
enum Decision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Done = 'done';
}
