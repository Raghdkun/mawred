<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Models\WhatsAppContact;

/** Stops all WhatsApp messages to this person and forgets the number. */
final readonly class RemoveWhatsAppNumber
{
    public function handle(User $user): void
    {
        WhatsAppContact::query()->where('user_id', $user->id)->delete();
        activity('notifications')->causedBy($user)->event('whatsapp.removed')->log('WhatsApp number removed');
    }
}
