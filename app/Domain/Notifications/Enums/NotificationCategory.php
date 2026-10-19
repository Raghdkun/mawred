<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

/**
 * Notification categories (ADR-0016). Critical categories ignore quiet hours and the digest; the
 * in-app copy of a critical message, and security email, can't be switched off.
 */
enum NotificationCategory: string
{
    case Approvals = 'approvals';
    case Requests = 'requests';
    case Documents = 'documents';
    case Schedule = 'schedule';
    case Hiring = 'hiring';
    case Training = 'training';
    case Reports = 'reports';
    case Security = 'security';
    case Integrations = 'integrations';

    public function isCritical(): bool
    {
        return $this === self::Approvals || $this === self::Security;
    }

    /**
     * Channels this category can use (WhatsApp needs a verified number, D58).
     *
     * @return list<string>
     */
    public function channels(): array
    {
        return ['in_app', 'email', 'whatsapp'];
    }

    public function canDisable(string $channel): bool
    {
        return (! $this->isCritical() || $channel !== 'in_app') && ($this !== self::Security || $channel !== 'email');
    }
}
