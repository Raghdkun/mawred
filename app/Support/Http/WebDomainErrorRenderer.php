<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Errors\DomainRuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Web (Inertia) side of domain rule violations: flashed back as form errors so the page shows
 * them inline. `error` carries the general message; `details` map onto fields.
 */
final class WebDomainErrorRenderer
{
    public function __invoke(DomainRuleViolation $e, Request $request): ?RedirectResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        $errors = ['error' => $e->getMessage()];

        foreach ($e->details as $field => $messages) {
            $errors[$field] = implode(' ', $messages);
        }

        return back()->withErrors($errors)->with('error_code', $e->errorCode);
    }
}
