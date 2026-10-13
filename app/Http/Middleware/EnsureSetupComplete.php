<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use App\Domain\Setup\Support\SetupState;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the first-run wizard is finished (brief §4), Super Admins are sent to the wizard and
 * everyone else sees a friendly "being set up" page. Sign-in and account security stay reachable.
 */
final readonly class EnsureSetupComplete
{
    private const array ALWAYS_ALLOWED = [
        'setup.*', 'login*', 'logout', 'password.*', 'two-factor.*', 'passkey.*',
        'security.edit', 'user-password.update', 'appearance.*', 'locale.*', 'verification.*',
        'storage.*', 'well-known.*',
    ];

    public function __construct(private SetupState $setup) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs(...self::ALWAYS_ALLOWED) || $this->setup->isComplete()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->can('setup.configure')) {
            return to_route('setup.show');
        }

        return Inertia::render('setup/pending')->toResponse($request)->setStatusCode(503);
    }
}
