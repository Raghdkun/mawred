<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

/** People see published shifts only; any edit puts a shift back to draft until it is published. */
enum ShiftStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
