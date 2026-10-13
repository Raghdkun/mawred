<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Setup;

use App\Concerns\PasswordValidationRules;
use App\Domain\Access\Models\User;
use App\Domain\Setup\Actions\IssueOwnerSetupLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The first Super Admin opens the one-time link printed by `mawred:install` and sets a password
 * (D4). Two-factor enrolment is then enforced before the wizard opens.
 */
final class OwnerClaimController extends Controller
{
    use PasswordValidationRules;

    public function show(string $token, IssueOwnerSetupLink $links): Response
    {
        $owner = $links->resolve($token);

        abort_unless($owner instanceof User, 404);

        return Inertia::render('setup/claim', [
            'token' => $token,
            'name' => $owner->name,
            'email' => $owner->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, string $token, IssueOwnerSetupLink $links): RedirectResponse
    {
        $owner = $links->resolve($token);

        abort_unless($owner instanceof User, 404);

        $request->validate(['password' => $this->passwordRules()]);

        $owner->forceFill([
            'password' => $request->string('password')->toString(),
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        $links->consume();

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();

        return to_route('setup.show');
    }
}
