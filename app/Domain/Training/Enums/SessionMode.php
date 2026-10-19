<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

enum SessionMode: string
{
    case Onsite = 'onsite';
    case Online = 'online';
}
