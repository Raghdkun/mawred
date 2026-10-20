<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Errors\DomainRuleViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Web (Inertia) side of domain rule violations: flashed back as form errors so the page shows
 * them inline. `error` carries the general message; `details` map onto fields. JSON calls to
 * web routes get the same details as a 422 body.
 */
final class WebDomainErrorRenderer
{
    public function __invoke(DomainRuleViolation $e, Request $request): RedirectResponse|JsonResponse|null
    {
        if ($request->is('api/*')) {
            return null;
        }

        // A JSON call to a web route (a builder preview, an upload): Laravel's validation shape.
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $e->getMessage(), 'errors' => (object) $e->details, 'code' => $e->errorCode], $e->status);
        }

        $errors = ['error' => $e->getMessage()];

        foreach ($e->details as $field => $messages) {
            $errors[$field] = implode(' ', $messages);
        }

        return back()->withErrors($errors)->with('error_code', $e->errorCode);
    }
}
