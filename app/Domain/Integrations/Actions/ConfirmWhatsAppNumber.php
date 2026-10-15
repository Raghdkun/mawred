<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Support\Errors\DomainRuleViolation;

/** Confirms the code; the number then receives WhatsApp messages (D58). Five tries per code. */
final readonly class ConfirmWhatsAppNumber
{
    public const int MAX_ATTEMPTS = 5;

    public function handle(User $user, string $code): WhatsAppContact
    {
        $contact = WhatsAppContact::query()->where('user_id', $user->id)->first();
        $fail = fn (string $message): DomainRuleViolation => new DomainRuleViolation('whatsapp.code_invalid', $message, ['code' => [$message]]);

        if (! $contact instanceof WhatsAppContact || $contact->code_hash === null || $contact->code_expires_at === null || $contact->code_expires_at->isPast()) {
            throw $fail(__('That code has expired. Send a new one.'));
        }

        if ($contact->code_attempts >= self::MAX_ATTEMPTS) {
            throw $fail(__('Too many wrong codes. Send a new one.'));
        }

        if (! hash_equals($contact->code_hash, StartWhatsAppVerification::hash(trim($code)))) {
            $contact->increment('code_attempts');

            throw $fail(__('That code is not right.'));
        }

        $contact->forceFill(['verified_at' => now(), 'code_hash' => null, 'code_expires_at' => null, 'code_attempts' => 0])->save();
        activity('notifications')->causedBy($user)->event('whatsapp.verified')->withProperties(['phone' => '••••'.mb_substr($contact->phone_e164, -4)])->log('WhatsApp number verified');

        return $contact;
    }
}
