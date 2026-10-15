<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Enums;

/** The outside services Mawred connects to (ADR-0015). One connection each, named `default`. */
enum IntegrationType: string
{
    case Email = 'email';
    case WhatsAppOfficial = 'whatsapp_official';
    case WhatsAppWaha = 'whatsapp_waha';
    case Cognito = 'cognito';

    public function label(?string $locale = null): string
    {
        return match ($this) {
            self::Email => __('Email', [], $locale),
            self::WhatsAppOfficial => __('WhatsApp (Official)', [], $locale),
            self::WhatsAppWaha => __('Unofficial — WhatsApp Web (WAHA)', [], $locale),
            self::Cognito => __('Cognito Forms', [], $locale),
        };
    }
}
