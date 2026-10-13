<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\UserSession;
use App\Support\Actors\Actor;

/**
 * Signs a browser/device out. Enforced on that session's next request (TrackUserSession).
 */
final readonly class RevokeUserSession
{
    public function handle(Actor $actor, UserSession $session): void
    {
        if ($session->revoked_at !== null) {
            return;
        }

        $session->forceFill(['revoked_at' => now()])->save();

        activity('auth')
            ->causedBy($actor->user)
            ->performedOn($session)
            ->event('session.revoked')
            ->withProperties(['session' => $session->ulid])
            ->log('Session revoked');
    }
}
