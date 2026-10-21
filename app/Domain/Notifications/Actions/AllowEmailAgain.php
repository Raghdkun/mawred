<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Models\EmailSuppression;
use App\Support\Actors\Actor;

/** Takes an address off the do-not-email list (D127), after the person fixed their mailbox. */
final readonly class AllowEmailAgain
{
    public function handle(Actor $actor, EmailSuppression $suppression): void
    {
        $suppression->delete();
    }
}
