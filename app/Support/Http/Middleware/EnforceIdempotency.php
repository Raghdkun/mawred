<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Support\Errors\DomainRuleViolation;
use App\Support\Http\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` support for mutating API requests (ADR-0012).
 *
 * - same key + same request  → the stored response is replayed
 * - same key + different body → 422 `idempotency.key_reused`
 * - same key still running    → 409 `idempotency.in_progress`
 * - 5xx responses are not stored, so the client may retry with the same key
 */
final class EnforceIdempotency
{
    public const string HEADER = 'Idempotency-Key';

    private const array MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const int TTL_HOURS = 24;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        if (! in_array($request->getMethod(), self::MUTATING, true)) {
            return $next($request);
        }

        $key = $request->headers->get(self::HEADER);

        if (! is_string($key) || $key === '') {
            if ($mode === 'required') {
                throw new DomainRuleViolation('idempotency.key_required', __('An Idempotency-Key header is required.'), status: 428);
            }

            return $next($request);
        }

        if (strlen($key) > 255) {
            throw new DomainRuleViolation('idempotency.key_invalid', __('The Idempotency-Key header is too long.'), status: 400);
        }

        $owner = $this->owner($request);
        $hash = hash('sha256', $request->getMethod().'|'.$request->path().'|'.$request->getContent());

        $existing = IdempotencyKey::query()
            ->where('owner', $owner)
            ->where('key', $key)
            ->first();

        if ($existing !== null && $existing->expires_at->isFuture()) {
            return $this->replay($existing, $hash);
        }

        $existing?->delete();

        try {
            $record = IdempotencyKey::query()->create([
                'owner' => $owner,
                'key' => $key,
                'request_hash' => $hash,
                'status' => 'processing',
                'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->inProgress();
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $record->delete();

            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            $record->delete();

            return $response;
        }

        $record->update([
            'status' => 'completed',
            'response_status' => $response->getStatusCode(),
            'response_headers' => ['Content-Type' => $response->headers->get('Content-Type')],
            'response_body' => (string) $response->getContent(),
        ]);

        return $response;
    }

    private function replay(IdempotencyKey $existing, string $hash): Response
    {
        if (! hash_equals($existing->request_hash, $hash)) {
            throw new DomainRuleViolation('idempotency.key_reused', __('This Idempotency-Key was already used for a different request.'));
        }

        if ($existing->status !== 'completed') {
            throw $this->inProgress();
        }

        return new Response((string) $existing->response_body, $existing->response_status ?? 200, [
            ...array_filter($existing->response_headers ?? []),
            'Idempotent-Replayed' => 'true',
        ]);
    }

    private function inProgress(): DomainRuleViolation
    {
        return new DomainRuleViolation('idempotency.in_progress', __('A request with this Idempotency-Key is still being processed.'), status: 409);
    }

    private function owner(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return 'ip:'.$request->ip();
        }

        // Bearer tokens (Flutter, bots) get per-token keys; cookie-authenticated SPA calls are per user.
        return $request->bearerToken() !== null
            ? 'token:'.$this->scalar($user->currentAccessToken()->getKey())
            : 'user:'.$this->scalar($user->getAuthIdentifier());
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
