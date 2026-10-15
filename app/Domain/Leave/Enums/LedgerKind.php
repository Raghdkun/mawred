<?php

declare(strict_types=1);

namespace App\Domain\Leave\Enums;

/**
 * Ledger movements. Accrual entries add up to what the calculator says was earned so far, so
 * re-running accrual (or changing a rule) only posts the difference.
 */
enum LedgerKind: string
{
    case Accrual = 'accrual';
    case Cap = 'cap';
    case CarryOver = 'carry_over';
    case CarryExpiry = 'carry_expiry';
    case Adjustment = 'adjustment';
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Accrual => __('Earned'),
            self::Cap => __('Over the maximum balance'),
            self::CarryOver => __('Carried over'),
            self::CarryExpiry => __('Carry-over expired'),
            self::Adjustment => __('Adjustment'),
            self::Debit => __('Leave taken'),
            self::Credit => __('Leave given back'),
        };
    }
}
