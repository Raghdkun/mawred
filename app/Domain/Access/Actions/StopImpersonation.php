<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\User;

final readonly class StopImpersonation
{
    public function handle(User $impersonator, ?User $target): void
    {
        $logger = activity('auth')->causedBy($impersonator)->event('impersonation.stopped');

        if ($target instanceof User) {
            $logger->performedOn($target);
        }

        $logger
            ->log('Impersonation stopped');
    }
}
