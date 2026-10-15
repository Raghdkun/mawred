<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Enums;

/** Health badge states (tokens: health-*). `unverified` = saved but not checked yet. */
enum IntegrationStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Disconnected = 'disconnected';
    case NotConfigured = 'not_configured';
    case Unverified = 'unverified';

    public function label(?string $locale = null): string
    {
        return match ($this) {
            self::Healthy => __('healthy', [], $locale),
            self::Degraded => __('degraded', [], $locale),
            self::Disconnected => __('disconnected', [], $locale),
            self::NotConfigured => __('not configured', [], $locale),
            self::Unverified => __('not checked yet', [], $locale),
        };
    }

    public function isProblem(): bool
    {
        return $this === self::Degraded || $this === self::Disconnected;
    }
}
