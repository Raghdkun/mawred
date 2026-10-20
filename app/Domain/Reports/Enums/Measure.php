<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

/** What a grouped custom report counts or adds up per group. */
enum Measure: string
{
    case Count = 'count';
    case Sum = 'sum';
    case Avg = 'avg';
}
