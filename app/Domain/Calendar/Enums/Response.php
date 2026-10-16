<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Enums;

/** An invitee's reply (D75). Declined events stay visible but don't count as busy. */
enum Response: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';

    public function isBusy(): bool
    {
        return $this !== self::Declined;
    }

    public function label(?string $locale = null): string
    {
        $label = match ($this) {
            self::Pending => __('Not replied', [], $locale),
            self::Accepted => __('Going', [], $locale),
            self::Tentative => __('Maybe', [], $locale),
            self::Declined => __('Not going', [], $locale),
        };

        return is_string($label) ? $label : $this->value;
    }
}
