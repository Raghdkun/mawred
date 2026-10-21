<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Notifications\Models\EmailSuppression;
use App\Support\Actors\Actor;

/**
 * Takes one Postmark webhook (D127): a hard bounce or a spam complaint puts the address on the
 * do-not-email list; soft bounces, deliveries and other records are ignored. Idempotent: a repeat
 * keeps the first entry.
 *
 * @see https://postmarkapp.com/developer/webhooks/bounce-webhook
 */
final readonly class RecordEmailBounce
{
    /** Postmark bounce types that mean the address can't receive mail. */
    public const array HARD = ['HardBounce', 'BadEmailAddress', 'ManuallyDeactivated', 'SpamNotification', 'Blocked'];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Actor $actor, array $payload): ?EmailSuppression
    {
        $record = $payload['RecordType'] ?? null;
        $email = is_string($payload['Email'] ?? null) ? mb_strtolower(trim($payload['Email'])) : '';

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $reason = match (true) {
            $record === 'SpamComplaint' => 'spam_complaint',
            $record === 'Bounce' && in_array($payload['Type'] ?? null, self::HARD, true) => 'hard_bounce',
            default => null,
        };

        if ($reason === null) {
            return null;
        }

        $detail = is_string($payload['Description'] ?? null) ? mb_substr($payload['Description'], 0, 300) : null;
        $suppression = EmailSuppression::query()->firstOrCreate(['email' => $email], ['reason' => $reason, 'detail' => $detail, 'source' => 'postmark']);

        if ($suppression->wasRecentlyCreated) {
            activity('integrations')->performedOn($suppression)->event('email.suppressed')->withProperties(['reason' => $reason])->log('Address stopped receiving email');
        }

        return $suppression;
    }
}
