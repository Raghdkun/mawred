<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Actions;

use App\Domain\Calendar\Enums\ParticipantRole;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Models\EventParticipant;
use App\Support\Errors\DomainRuleViolation;

/**
 * An invitee's reply (D75), from the app, a one-tap email link or a WhatsApp button. A reply
 * covers the whole series; replying again changes it.
 */
final readonly class RespondToEvent
{
    public function handle(EventParticipant $participant, Response $response): EventParticipant
    {
        if ($participant->role === ParticipantRole::Organizer || $response === Response::Pending) {
            throw new DomainRuleViolation('calendar.cannot_reply', __('The organiser is always going.'));
        }

        $participant->forceFill(['response' => $response, 'responded_at' => now()])->save();

        return $participant;
    }
}
