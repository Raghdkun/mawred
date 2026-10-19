<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

/** How a report column is formatted on screen and in files. */
enum ColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Decimal = 'decimal';
    case Percent = 'percent';
    case Date = 'date';
    case Minutes = 'minutes';
}
