<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

/**
 * Button words for one-tap actions in emails and WhatsApp: approval decisions (ADR-0009) and
 * meeting replies (D75). The first action of a message is the primary button.
 */
final class ActionLabels
{
    public static function label(string $action, ?string $locale = null): string
    {
        $label = match ($action) {
            'approved' => __('Approve', [], $locale),
            'rejected' => __('Reject', [], $locale),
            'accepted' => __('Going', [], $locale),
            'tentative' => __('Maybe', [], $locale),
            'declined' => __('Not going', [], $locale),
            default => $action,
        };

        return is_string($label) ? $label : $action;
    }
}
