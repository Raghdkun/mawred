<?php

declare(strict_types=1);

namespace App\Domain\Setup\Enums;

/**
 * First-run wizard steps in order (brief §4, handoff screen 2).
 */
enum SetupStep: string
{
    case Welcome = 'welcome';
    case Company = 'company';
    case Languages = 'languages';
    case Branch = 'branch';
    case Policies = 'policies';
    case Admin = 'admin';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Cognito = 'cognito';
    case Review = 'review';

    /** Required steps can't be skipped; everything else can be skipped and revisited later. */
    public function isRequired(): bool
    {
        return in_array($this, [self::Company, self::Admin], true);
    }

    /** Steps whose forms ship in a later phase render as "available later" until then. */
    public function availableInPhase(): int
    {
        return match ($this) {
            self::WhatsApp, self::Cognito => 4,
            default => 0,
        };
    }

    public function number(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
