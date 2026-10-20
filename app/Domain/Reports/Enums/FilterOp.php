<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

/**
 * The conditions a custom report's filters can use, typed by field (no free-text formulas):
 * text `is`/`contains`, choices `in`, dates `between`/`last_days`, numbers `is`/`gte`/`lte`.
 */
enum FilterOp: string
{
    case Is = 'is';
    case Contains = 'contains';
    case In = 'in';
    case Between = 'between';
    case LastDays = 'last_days';
    case Gte = 'gte';
    case Lte = 'lte';
}
