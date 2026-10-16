<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum MessageDirection: string
{
    case Out = 'out';
    case In = 'in';
}
