<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Integrations\WhatsApp\OutgoingMessage;
use App\Domain\Integrations\WhatsApp\WhatsAppGateway;
use App\Support\Errors\DomainRuleViolation;

/**
 * Sends a test message to the admin's own verified number (handoff 40 "send test message").
 * Official uses the chosen approved template (Meta's `hello_world` by default) unless the
 * 24-hour window is open.
 */
final readonly class SendWhatsAppTest
{
    public function __construct(private WhatsAppGateway $gateway) {}

    public function handle(User $user, IntegrationType $driver, ?string $template = null): void
    {
        $contact = WhatsAppContact::query()->where('user_id', $user->id)->whereNotNull('verified_at')->first();

        if (! $contact instanceof WhatsAppContact) {
            throw new DomainRuleViolation('whatsapp.no_number', __('Add and confirm your own WhatsApp number in notification settings first.'));
        }

        $locale = $user->locale === 'ar' ? 'ar' : 'en';
        $text = __('This is a test message from Mawred.', [], $locale);
        $result = $this->gateway->send($driver, $user, 'whatsapp.test', new OutgoingMessage(
            $contact->phone_e164,
            $text,
            $driver === IntegrationType::WhatsAppOfficial ? ['name' => $template ?? 'hello_world', 'language' => $template === null ? 'en_US' : $locale, 'params' => [], 'buttons' => []] : null,
            inServiceWindow: $driver === IntegrationType::WhatsAppWaha || $contact->inServiceWindow(),
        ));

        if (! $result->sent) {
            throw new DomainRuleViolation('whatsapp.test_failed', __('The test message could not be sent: :reason', ['reason' => $result->error ?? '']));
        }
    }
}
