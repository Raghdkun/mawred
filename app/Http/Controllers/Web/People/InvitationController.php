<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Concerns\PasswordValidationRules;
use App\Domain\People\Actions\AcceptInvitation;
use App\Domain\People\Models\UserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An invited employee opens their one-time link and sets a password.
 */
final class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(string $token): Response
    {
        $invitation = UserInvitation::findPending($token);

        abort_unless($invitation instanceof UserInvitation && $invitation->employee !== null, 404);

        return Inertia::render('auth/invitation', [
            'token' => $token,
            'name' => $invitation->employee->displayName(),
            'email' => $invitation->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $invitation = UserInvitation::findPending($token);

        abort_unless($invitation instanceof UserInvitation, 404);

        $request->validate(['password' => $this->passwordRules()]);

        $locale = $request->session()->get(SetLocale::SESSION_KEY);
        $user = $accept->handle(
            $invitation,
            $request->string('password')->toString(),
            in_array($locale, SetLocale::SUPPORTED, true) ? $locale : app()->getLocale(),
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
