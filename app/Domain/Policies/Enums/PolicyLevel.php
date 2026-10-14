<?php

declare(strict_types=1);

namespace App\Domain\Policies\Enums;

/**
 * Assignment levels, general to specific (ADR-0010). Employment type beats grade (D13). The UI
 * draws six levels: grade and employment type share one card (D30).
 */
enum PolicyLevel: string
{
    case Global = 'global';
    case Branch = 'branch';
    case Department = 'department';
    case Position = 'position';
    case Grade = 'grade';
    case EmploymentType = 'employment_type';
    case Employee = 'employee';

    public function rank(): int
    {
        return match ($this) {
            self::Global => 1,
            self::Branch => 2,
            self::Department => 3,
            self::Position => 4,
            self::Grade => 5,
            self::EmploymentType => 6,
            self::Employee => 7,
        };
    }

    public function needsTarget(): bool
    {
        return $this !== self::Global;
    }
}
