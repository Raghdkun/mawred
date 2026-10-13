<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Actions\StartUserSession;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps "Active sessions" current and enforces revocation (brief §6).
 */
final readonly class TrackUserSession
{
    private const int TOUCH_AFTER_SECONDS = 300;

    public function __construct(private StartUserSession $start) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $ulid = $request->session()->get(UserSession::SESSION_KEY);
        $session = is_string($ulid) ? UserSession::query()->whereUlid($ulid)->first() : null;

        if ($session instanceof UserSession && $session->user_id !== $user->id) {
            $session = null; // a different account signed in on this browser: track it separately
        }

        if ($session instanceof UserSession && $session->revoked_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('login'));
        }

        if (! $session instanceof UserSession) {
            $session = $this->start->handle($user, $request->ip(), $request->userAgent());
            $request->session()->put(UserSession::SESSION_KEY, $session->ulid);
        } elseif ($session->last_seen_at->diffInSeconds(now()) > self::TOUCH_AFTER_SECONDS) {
            $session->forceFill(['last_seen_at' => now(), 'ip' => $request->ip()])->save();
        }

        return $next($request);
    }
}
