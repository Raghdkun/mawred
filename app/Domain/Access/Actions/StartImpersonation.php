<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\User;

/**
 * Records the start of an impersonation (brief §6). The HTTP layer stores the session state;
 * every audit entry made meanwhile carries the impersonator's id.
 */
final readonly class StartImpersonation
{
    public function handle(User $impersonator, User $target): void
    {
        activity('auth')
            ->causedBy($impersonator)
            ->performedOn($target)
            ->event('impersonation.started')
            ->log('Impersonation started');
    }
}
