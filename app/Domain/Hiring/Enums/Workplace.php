<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum Workplace: string
{
    case Onsite = 'onsite';
    case Hybrid = 'hybrid';
    case Remote = 'remote';
}
