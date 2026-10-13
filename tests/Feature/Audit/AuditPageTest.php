<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SyncRoleGrants;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => seedAccess());

it('requires audit.view', function (): void {
    $this->actingAs(userWithRoles('employee'))->get(route('audit.index'))->assertForbidden();
});

it('shows entries to auditors with filters and no internal ids', function (): void {
    $auditor = userWithRoles('auditor');
    $auditor->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    activity('auth')->causedBy($auditor)->event('login')->log('Signed in');

    $response = $this->actingAs($auditor)->get(route('audit.index', ['log' => 'auth']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('audit/index')
            ->where('entries.data', fn ($entries) => collect($entries)->every(fn ($entry): bool => $entry['log'] === 'auth'))
            ->where('entries.data.0.actor.id', $auditor->ulid));

    expect($response->getContent())->not->toMatch('/"(id|subject_id|causer_id)":\s*\d+[,}]/');
});

it('limits the own scope to what the user did', function (): void {
    resolve(SyncRoleGrants::class)->handle(Role::query()->where('name', 'employee')->sole(), ['audit.view' => Scope::Own]);
    $me = userWithRoles('employee');
    $other = User::factory()->create();
    activity('test')->causedBy($me)->log('mine');
    activity('test')->causedBy($other)->log('theirs');

    $this->actingAs($me)->get(route('audit.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('entries.data', fn ($entries) => collect($entries)->pluck('description')->doesntContain('theirs')));
});
