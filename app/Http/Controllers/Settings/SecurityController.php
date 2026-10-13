<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\Actions\RevokeUserSession;
use App\Domain\Access\Data\UserSessionData;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserSession;
use App\Domain\Access\Support\LoginHistory;
use App\Domain\Audit\Models\Activity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request, #[CurrentUser] User $user, LoginHistory $history): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey): array => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at?->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'sessions' => UserSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->latest('last_seen_at')
                ->limit(20)
                ->get()
                ->map(fn (UserSession $session): UserSessionData => UserSessionData::fromSession($session, $this->currentSessionUlid($request))),
            'loginHistory' => $history->recent($user)->map(fn (Activity $activity): array => [
                'event' => $activity->event,
                'at' => $activity->created_at?->toIso8601String(),
                'ip' => $activity->ip,
                'channel' => $activity->channel,
            ]),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Sign out one of your own browsers/devices.
     */
    public function revokeSession(Request $request, UserSession $session, #[CurrentUser] User $user, RevokeUserSession $revoke): RedirectResponse
    {
        abort_unless($session->user_id === $user->id, 404);

        $revoke->handle(Actor::user($user), $session);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('That session has been signed out.')]);

        return back();
    }

    private function currentSessionUlid(Request $request): ?string
    {
        $ulid = $request->session()->get(UserSession::SESSION_KEY);

        return is_string($ulid) ? $ulid : null;
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
