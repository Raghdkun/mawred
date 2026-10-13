<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;

final readonly class UserPolicy
{
    public function __construct(private ScopeResolver $resolver) {}

    /**
     * Super Admin only; never another Super Admin, never yourself, never API-only or inactive
     * accounts (brief §6).
     */
    public function impersonate(User $actor, User $target): bool
    {
        if ($actor->is($target) || ! $this->resolver->for($actor)->isSuperAdmin) {
            return false;
        }

        $targetAccess = $this->resolver->for($target);

        return ! $targetAccess->isSuperAdmin && ! $targetAccess->apiOnly && $target->status === 'active';
    }
}
