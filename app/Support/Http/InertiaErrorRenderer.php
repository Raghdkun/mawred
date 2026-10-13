<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders HTTP errors for browser requests as the illustrated error page (brief §17). Local
 * environments keep Laravel's debug page for 500s.
 */
final class InertiaErrorRenderer
{
    private const array STATUSES = [403, 404, 419, 429, 500, 503];

    public function __invoke(Response $response, \Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        if ($request->is('api/*') || $request->expectsJson() || ! in_array($status, self::STATUSES, true)) {
            return $response;
        }

        if ($status === 500 && config('app.debug')) {
            return $response;
        }

        return Inertia::render('errors/show', [
            'status' => $status,
            'requestId' => Context::get('request_id'),
        ])->toResponse($request)->setStatusCode($status);
    }
}
