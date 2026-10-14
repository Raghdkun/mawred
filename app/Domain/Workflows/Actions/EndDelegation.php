<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Models\Delegation;
use App\Support\Actors\Actor;

/** Stops a delegation early (kept, soft-deleted, for the record). */
final readonly class EndDelegation
{
    public function handle(Actor $actor, Delegation $delegation): void
    {
        $delegation->delete();
    }
}
