<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Audit\Support\AuditContext;
use App\Support\Actors\Channel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps the request's channel, client and impersonator into the audit context (ADR-0006).
 */
final class SetAuditContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $impersonator = $request->hasSession() ? $request->session()->get('impersonator_id') : null;

        AuditContext::forRequest(
            channel: $request->is('api/*') && $request->bearerToken() !== null ? Channel::Api : Channel::Web,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            impersonatorId: is_int($impersonator) ? $impersonator : null,
        );

        return $next($request);
    }
}
