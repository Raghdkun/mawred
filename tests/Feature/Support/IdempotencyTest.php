<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->counter = 0;

    Route::middleware(['api', 'auth:sanctum', 'idempotent'])->post('api/v1/_test/things', function () {
        $this->counter++;

        return response()->json(['count' => $this->counter], 201);
    });

    $this->actingAs(User::factory()->create(), 'sanctum');
});

it('replays the stored response for a repeated key', function (): void {
    $first = $this->postJson('/api/v1/_test/things', ['a' => 1], ['Idempotency-Key' => 'k-1']);
    $second = $this->postJson('/api/v1/_test/things', ['a' => 1], ['Idempotency-Key' => 'k-1']);

    $first->assertCreated()->assertJson(['count' => 1]);
    $second->assertCreated()->assertJson(['count' => 1])->assertHeader('Idempotent-Replayed', 'true');
    expect($this->counter)->toBe(1);
});

it('rejects a reused key with a different payload', function (): void {
    $this->postJson('/api/v1/_test/things', ['a' => 1], ['Idempotency-Key' => 'k-2'])->assertCreated();

    $this->postJson('/api/v1/_test/things', ['a' => 2], ['Idempotency-Key' => 'k-2'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'idempotency.key_reused');
});

it('does not interfere when no key is sent', function (): void {
    $this->postJson('/api/v1/_test/things')->assertJson(['count' => 1]);
    $this->postJson('/api/v1/_test/things')->assertJson(['count' => 2]);
});

it('scopes keys per owner', function (): void {
    $this->postJson('/api/v1/_test/things', [], ['Idempotency-Key' => 'shared'])->assertJson(['count' => 1]);

    $this->actingAs(User::factory()->create(), 'sanctum');

    $this->postJson('/api/v1/_test/things', [], ['Idempotency-Key' => 'shared'])->assertJson(['count' => 2]);
});
