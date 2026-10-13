<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Support\Errors\DomainRuleViolation;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA enforced per role (brief §6): if any of the user's roles requires it and the user has not
 * confirmed TOTP yet, everything except the enrolment flow is blocked.
 */
final readonly class EnsureTwoFactorEnrolled
{
    /** Routes that must stay reachable while enrolling. */
    private const array ALLOWED = [
        'security.edit', 'user-password.update', 'logout',
        'two-factor.*', 'password.confirm*', 'passkey.*', 'appearance.*', 'locale.*',
    ];

    public function __construct(private ScopeResolver $resolver) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || $user->two_factor_confirmed_at !== null
            || ($request->hasSession() && $request->session()->has(ApplyImpersonation::TARGET))
            || $request->routeIs(...self::ALLOWED)
            || ! $this->resolver->for($user)->requiresTwoFactor) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            throw new DomainRuleViolation('access.two_factor_required', __('Two-factor authentication must be enabled for your role.'), status: 403);
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => __('Your role requires two-factor authentication. Please set it up to continue.')]);

        return to_route('security.edit');
    }
}
