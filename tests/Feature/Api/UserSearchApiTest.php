<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => seedAccess());

it('requires authentication', function (): void {
    $this->getJson('/api/v1/users')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
});

it('requires access.view and answers in the error envelope', function (): void {
    Sanctum::actingAs(userWithRoles('employee'));

    $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
});

it('searches users with ULIDs and cursor pagination', function (): void {
    $admin = userWithRoles('hr_admin');
    $admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    User::factory()->count(3)->create();
    $target = User::factory()->create(['name' => 'Zainab Haddad']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/users?search=zainab')
        ->assertOk()
        ->assertJsonPath('data.0.id', $target->ulid)
        ->assertJsonMissingPath('data.0.password')
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'status']], 'meta' => ['next_cursor', 'prev_cursor', 'per_page']]);

    $this->getJson('/api/v1/users?per_page=2')->assertJsonPath('meta.per_page', 2)->assertJsonCount(2, 'data');
});
