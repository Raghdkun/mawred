<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Models\UserInvitation;

final readonly class RevokeInvitation
{
    public function handle(UserInvitation $invitation): void
    {
        if ($invitation->isPending()) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }
    }
}
