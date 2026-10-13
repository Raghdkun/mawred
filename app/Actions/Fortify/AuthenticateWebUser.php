<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

/**
 * Web sign-in credential check (Fortify::authenticateUsing). Suspended, invited-but-inactive and
 * API-only accounts (Integration Bot) can never sign in through the browser.
 */
final readonly class AuthenticateWebUser
{
    public function __construct(private ScopeResolver $resolver) {}

    public function __invoke(Request $request): ?User
    {
        $user = User::query()
            ->where(Fortify::username(), $request->string(Fortify::username())->lower()->toString())
            ->first();

        if (! $user instanceof User || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return null;
        }

        if ($user->status !== 'active' || $this->resolver->for($user)->apiOnly) {
            return null;
        }

        return $user;
    }
}
