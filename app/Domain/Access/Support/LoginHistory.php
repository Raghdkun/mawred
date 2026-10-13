<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Models\Activity;
use Illuminate\Support\Collection;

/**
 * A user's own recent sign-in and account-security events (brief §6: login history). Personal
 * data about yourself, so it is not governed by the `audit.view` scope.
 */
final class LoginHistory
{
    /**
     * @return Collection<int, Activity>
     */
    public function recent(User $user, int $limit = 20): Collection
    {
        return Activity::query()
            ->where('log_name', 'auth')
            ->where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
