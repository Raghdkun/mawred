<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a Super Admin impersonates someone, the request runs as the target user. The session
 * itself stays the admin's, so stopping (or signing out) always returns to the admin.
 */
final class ApplyImpersonation
{
    public const string IMPERSONATOR = 'impersonator_id';

    public const string TARGET = 'impersonating_id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(self::TARGET)) {
            return $next($request);
        }

        $guard = Auth::guard('web');
        // The session's own login key is the real (admin) account, whatever the guard holds.
        $sessionUserId = $request->session()->get($guard->getName());
        $targetId = $request->session()->get(self::TARGET);
        $target = is_int($targetId) ? User::query()->find($targetId) : null;

        if (! is_numeric($sessionUserId)
            || $request->session()->get(self::IMPERSONATOR) !== (int) $sessionUserId
            || ! $target instanceof User
            || $target->status !== 'active') {
            $request->session()->forget([self::TARGET, self::IMPERSONATOR]);

            return $next($request);
        }

        $guard->setUser($target);

        return $next($request);
    }
}
