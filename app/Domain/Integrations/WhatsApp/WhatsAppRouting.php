<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WhatsApp;

use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\Connections;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Setup\Support\Settings;

/**
 * Delivery chain per notification category (ADR-0015 router): the steps tried in order until
 * one delivers. Default (D59): WhatsApp Official, then email, once Official is set up. The
 * Unofficial driver appears only where an admin adds it.
 */
final readonly class WhatsAppRouting
{
    public const array STEPS = ['whatsapp_official', 'whatsapp_waha', 'email'];

    public function __construct(private Settings $settings) {}

    /**
     * @return list<string>
     */
    public function chain(NotificationCategory $category): array
    {
        $saved = $this->settings->get('whatsapp.routing', []);
        $chain = is_array($saved) && is_array($saved[$category->value] ?? null) ? array_values(array_filter($saved[$category->value], fn (mixed $step): bool => is_string($step) && in_array($step, self::STEPS, true))) : null;

        if ($chain === null || $chain === []) {
            $chain = $this->configured(IntegrationType::WhatsAppOfficial) ? ['whatsapp_official', 'email'] : ['email'];
        }

        return in_array('email', $chain, true) ? $chain : [...$chain, 'email'];
    }

    /**
     * Every category's chain, for the routing step.
     *
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        $chains = [];

        foreach (NotificationCategory::cases() as $category) {
            $chains[$category->value] = $this->chain($category);
        }

        return $chains;
    }

    /**
     * Critical categories that would rely on the Unofficial driver alone for WhatsApp.
     *
     * @param  list<string>  $chain
     */
    public static function riskyCritical(NotificationCategory $category, array $chain): bool
    {
        return $category->isCritical() && in_array('whatsapp_waha', $chain, true) && ! in_array('whatsapp_official', $chain, true);
    }

    private function configured(IntegrationType $type): bool
    {
        $connection = Connections::find($type);

        return $connection instanceof IntegrationConnection && $connection->healthStatus() !== IntegrationStatus::NotConfigured;
    }
}
