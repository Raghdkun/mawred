<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Errors\DomainRuleViolation;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every API error in the envelope from ADR-0012:
 * { "error": { "code", "message", "details", "trace_id" } }.
 */
final class ApiErrorRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message, $details] = match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', __('The given data was invalid.'), $e->errors()],
            $e instanceof DomainRuleViolation => [$e->status, $e->errorCode, $e->getMessage(), $e->details],
            $e instanceof AuthenticationException => [401, 'unauthenticated', __('Unauthenticated.'), []],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $this->codeForStatus($e->getStatusCode()),
                $this->messageForStatus($e->getStatusCode()),
                [],
            ],
            default => [500, 'server_error', __('Something went wrong.'), []],
        };

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
                'trace_id' => Context::get('request_id'),
            ],
        ], $status, $headers);
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            419 => 'csrf_mismatch',
            429 => 'rate_limited',
            503 => 'unavailable',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };
    }

    private function messageForStatus(int $status): string
    {
        return match ($status) {
            403 => __('This action is unauthorized.'),
            404 => __('Not found.'),
            429 => __('Too many requests.'),
            default => __('Something went wrong.'),
        };
    }
}
