<?php

declare(strict_types=1);

namespace App\Domain\People\Enums;

/** Kinds of monthly allowance (D116); "other" carries its own label. */
enum AllowanceType: string
{
    case Housing = 'housing';
    case Transport = 'transport';
    case Food = 'food';
    case Phone = 'phone';
    case Education = 'education';
    case Other = 'other';
}
