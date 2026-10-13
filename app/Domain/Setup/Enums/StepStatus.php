<?php

declare(strict_types=1);

namespace App\Domain\Setup\Enums;

enum StepStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
