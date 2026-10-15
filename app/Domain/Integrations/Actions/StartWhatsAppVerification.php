<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Integrations\WhatsApp\OutgoingMessage;
use App\Domain\Integrations\WhatsApp\WhatsAppGateway;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Setup\Support\Settings;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Phone\Phones;

/**
 * Sends a one-time code over WhatsApp to the number someone wants to use (D58). Official needs
 * an approved authentication template (outside the 24-hour window); otherwise the Unofficial
 * driver sends plain text. Nothing else goes to the number until the code is confirmed.
 */
final readonly class StartWhatsAppVerification
{
    public const int TTL_MINUTES = 10;

    public function __construct(private WhatsAppGateway $gateway, private Settings $settings) {}

    public function handle(User $user, string $phone): WhatsAppContact
    {
        $region = Branch::withTrashed()->whereKey(Employee::query()->where('user_id', $user->id)->value('current_branch_id'))->value('phone_region');
        $e164 = Phones::toE164($phone, is_string($region) ? $region : null);

        if (! is_string($e164)) {
            throw new DomainRuleViolation('whatsapp.phone_invalid', __('Enter a valid mobile number.'), ['phone' => [__('Enter a valid mobile number.')]]);
        }

        $code = (string) random_int(100000, 999999);
        $contact = WhatsAppContact::query()->firstOrNew(['user_id' => $user->id]);

        if ($contact->exists && $contact->phone_e164 !== $e164) {
            $contact->verified_at = null;
        }

        $contact->forceFill([
            'phone_e164' => $e164,
            'code_hash' => self::hash($code),
            'code_expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'code_attempts' => 0,
        ])->save();

        $locale = $user->locale === 'ar' ? 'ar' : 'en';
        $text = __('Your Mawred verification code is :code. It expires in :minutes minutes.', ['code' => $code, 'minutes' => self::TTL_MINUTES], $locale);
        $template = $this->settings->get('whatsapp.verification_template');
        $official = is_string($template) && $template !== '' ? new OutgoingMessage($e164, $text, ['name' => $template, 'language' => $locale, 'params' => [$code], 'buttons' => []]) : null;

        $sent = ($official instanceof OutgoingMessage && $this->gateway->send(IntegrationType::WhatsAppOfficial, $user, 'whatsapp.verification', $official)->sent)
            || (WhatsAppGateway::usable(IntegrationType::WhatsAppWaha) instanceof IntegrationConnection && $this->gateway->send(IntegrationType::WhatsAppWaha, $user, 'whatsapp.verification', new OutgoingMessage($e164, $text))->sent);

        if (! $sent) {
            throw new DomainRuleViolation('whatsapp.code_not_sent', __('We couldn’t send a WhatsApp message to that number. Check it and try again, or ask HR whether WhatsApp is set up.'), ['phone' => [__('We couldn’t send a WhatsApp message to that number. Check it and try again, or ask HR whether WhatsApp is set up.')]]);
        }

        return $contact;
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, 'whatsapp-verification|'.FieldSpec::string(config('app.key')));
    }
}
