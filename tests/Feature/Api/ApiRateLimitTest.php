<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;

/*
| Phase 9a: every /api/v1 caller has a per-minute ceiling, answered in the API error envelope.
*/
it('limits each caller and answers in the error envelope', function (): void {
    seedAccess();
    $user = userWithRoles('employee');
    $other = userWithRoles('employee');

    foreach (range(1, 300) as $n) {
        RateLimiter::hit(md5('api'.'api:'.$user->id), 60);
    }

    $this->actingAs($user)->getJson('/api/v1/leave-types')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');

    // Someone else isn't affected.
    $this->actingAs($other)->getJson('/api/v1/leave-types')->assertOk();
});
