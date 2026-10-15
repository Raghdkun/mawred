<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

enum PunchKind: string
{
    case In = 'in';
    case Out = 'out';
}
