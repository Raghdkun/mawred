<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

enum AnchorType: string
{
    case Branch = 'branch';
    case Department = 'department';
}
