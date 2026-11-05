<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * A link where someone chooses their password (D136), through the same broker as "Forgot
 * password": only a hash of the token is stored, a new link replaces the previous one, and it lasts
 * `auth.passwords.users.expire` minutes.
 */
final readonly class IssuePasswordSetupLink
{
    public function handle(User $user): string
    {
        $token = Password::broker()->createToken($user);

        return route('password.reset', ['token' => $token, 'email' => $user->email]);
    }
}
