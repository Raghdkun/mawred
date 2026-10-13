<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserSession;

final readonly class StartUserSession
{
    public function handle(User $user, ?string $ip, ?string $userAgent): UserSession
    {
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return UserSession::query()->create([
            'user_id' => $user->id,
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
            'last_seen_at' => now(),
        ]);
    }
}
