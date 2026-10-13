<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserSession;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Session;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Login history and account-security events, written to the `auth` audit log (brief §6, ADR-0006).
 * Passwords and codes are never recorded; failed attempts record only the identifier tried.
 */
final class RecordAuthenticationEvents
{
    public function login(Login $event): void
    {
        $this->record($event->user, 'login', 'Signed in', ['guard' => $event->guard, 'remember' => $event->remember]);
    }

    public function failed(Failed $event): void
    {
        $identifier = $event->credentials['email'] ?? null;

        activity('auth')
            ->causedBy($event->user instanceof User ? $event->user : null)
            ->event('login.failed')
            ->withProperties(['identifier' => is_string($identifier) ? mb_strtolower($identifier) : null])
            ->log('Sign-in failed');
    }

    public function logout(Logout $event): void
    {
        $ulid = Session::get(UserSession::SESSION_KEY);

        if (is_string($ulid)) {
            UserSession::query()->whereUlid($ulid)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }

        $this->record($event->user, 'logout', 'Signed out');
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->record($event->user, 'password.reset', 'Password reset');
    }

    public function twoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->record($event->user, '2fa.enabled', 'Two-factor authentication enabled');
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->record($event->user, '2fa.disabled', 'Two-factor authentication disabled');
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->record($event->user, '2fa.failed', 'Two-factor code rejected');
    }

    public function recoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->record($event->user, '2fa.recovery_codes', 'Recovery codes regenerated');
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            Login::class => 'login',
            Failed::class => 'failed',
            Logout::class => 'logout',
            PasswordReset::class => 'passwordReset',
            TwoFactorAuthenticationConfirmed::class => 'twoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'twoFactorDisabled',
            TwoFactorAuthenticationFailed::class => 'twoFactorFailed',
            RecoveryCodesGenerated::class => 'recoveryCodesGenerated',
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function record(mixed $user, string $event, string $description, array $properties = []): void
    {
        $user = $user instanceof User ? $user : null;

        $logger = activity('auth')->causedBy($user)->event($event)->withProperties($properties);

        if ($user instanceof User) {
            $logger->performedOn($user);
        }

        $logger->log($description);
    }
}
