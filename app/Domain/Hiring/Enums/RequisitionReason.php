<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum RequisitionReason: string
{
    case New = 'new';
    case Replacement = 'replacement';
}
