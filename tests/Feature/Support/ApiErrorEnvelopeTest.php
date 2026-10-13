<?php

declare(strict_types=1);

use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('api')->prefix('api/v1/_test')->group(function (): void {
        Route::post('validate', fn () => request()->validate(['name' => ['required']]));
        Route::get('rule', fn () => throw new DomainRuleViolation('leave.insufficient_balance', 'Not enough balance.', ['days' => ['Too many']]));
        Route::get('boom', fn () => throw new RuntimeException('secret internals'));
    });
});

it('wraps validation errors in the envelope', function (): void {
    $this->postJson('/api/v1/_test/validate', [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['name'], 'trace_id']]);
});

it('exposes stable domain error codes', function (): void {
    $this->getJson('/api/v1/_test/rule')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'leave.insufficient_balance')
        ->assertJsonPath('error.details.days.0', 'Too many');
});

it('returns 404 envelopes for unknown routes and hides server errors', function (): void {
    $this->getJson('/api/v1/_test/nope')->assertNotFound()->assertJsonPath('error.code', 'not_found');

    config(['app.debug' => false]);

    $this->getJson('/api/v1/_test/boom')
        ->assertStatus(500)
        ->assertJsonPath('error.code', 'server_error')
        ->assertJsonMissing(['message' => 'secret internals']);
});

it('returns 401 envelope for unauthenticated api calls', function (): void {
    Route::middleware(['api', 'auth:sanctum'])->get('api/v1/_test/private', fn (): string => 'ok');

    $this->getJson('/api/v1/_test/private')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
});

it('echoes a request id as trace id and header', function (): void {
    $response = $this->getJson('/api/v1/_test/rule');

    expect($response->headers->get('X-Request-Id'))->toBe($response->json('error.trace_id'));
});
