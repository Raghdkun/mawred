<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Models\PushSubscription;

/**
 * Turns push on for one of a person's devices (D128). The same device subscribing again updates
 * its keys; a device that moved to another account follows the new owner.
 */
final readonly class SavePushSubscription
{
    /** Devices one person can have. */
    public const int MAX_PER_PERSON = 10;

    public function handle(User $user, string $endpoint, string $publicKey, string $authToken, string $encoding, ?string $userAgent): PushSubscription
    {
        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            ['user_id' => $user->id, 'endpoint' => $endpoint, 'public_key' => $publicKey, 'auth_token' => $authToken, 'content_encoding' => $encoding, 'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 200)],
        );

        // Keep the newest devices if someone collects too many.
        $keep = PushSubscription::query()->where('user_id', $user->id)->latest('updated_at')->limit(self::MAX_PER_PERSON)->pluck('id');
        PushSubscription::query()->where('user_id', $user->id)->whereNotIn('id', $keep)->delete();

        return $subscription;
    }
}
