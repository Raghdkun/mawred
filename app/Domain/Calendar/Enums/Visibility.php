<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Enums;

/** Private events show only as busy, even to people whose scope covers the participant (D76). */
enum Visibility: string
{
    case Normal = 'normal';
    case Private = 'private';
}
