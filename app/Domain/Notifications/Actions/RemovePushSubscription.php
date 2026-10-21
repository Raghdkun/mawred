<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Models\PushSubscription;

/** Turns push off for one device (D128); only its owner can. */
final readonly class RemovePushSubscription
{
    public function handle(User $user, string $endpoint): void
    {
        PushSubscription::query()->where('user_id', $user->id)->where('endpoint_hash', hash('sha256', $endpoint))->delete();
    }
}
