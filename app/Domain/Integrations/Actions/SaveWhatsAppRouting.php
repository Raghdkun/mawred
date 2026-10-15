<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\WhatsApp\WhatsAppRouting;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;

/**
 * Saves the delivery chain per category (routing step, handoff 40). Email always ends a chain
 * so nothing is lost when WhatsApp fails.
 */
final readonly class SaveWhatsAppRouting
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array<string, list<string>>  $chains
     */
    public function handle(Actor $actor, array $chains, ?string $verificationTemplate): void
    {
        $clean = [];

        foreach (NotificationCategory::cases() as $category) {
            $steps = array_values(array_unique(array_filter($chains[$category->value] ?? [], fn (string $step): bool => in_array($step, WhatsAppRouting::STEPS, true) && $step !== 'email')));
            $clean[$category->value] = [...$steps, 'email'];
        }

        $this->settings->set('whatsapp.routing', $clean);
        $this->settings->set('whatsapp.verification_template', $verificationTemplate === null || trim($verificationTemplate) === '' ? null : trim($verificationTemplate));

        activity('integrations')->causedBy($actor->user)->event('whatsapp.routing')->withProperties(['routing' => $clean])->log('WhatsApp routing saved');
    }
}
