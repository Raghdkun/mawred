<?php

declare(strict_types=1);

use App\Domain\Access\Models\Role;

beforeEach(function (): void {
    seedAccess();
    $this->root = userWithRoles('super_admin');
    $this->root->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
});

it('renders the permission matrix accessibly in both languages', function (string $locale): void {
    $this->root->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->root);

    visit('/access/roles/'.Role::query()->where('name', 'hr_officer')->sole()->ulid)
        ->wait(0.6)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('cycles a scope cell and shows the unsaved-changes bar', function (): void {
    $this->actingAs($this->root);

    visit('/access/roles/'.Role::query()->where('name', 'trainer')->sole()->ulid)
        ->click('[aria-label^="People: Delete"]')
        ->assertSee('1 unsaved change')
        ->assertNoJavaScriptErrors();
});

it('renders the setup wizard accessibly in both languages', function (string $locale): void {
    $this->markSetupIncomplete();
    $this->root->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->root);

    visit('/setup')
        ->wait(0.6)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('renders the audit log and account pages accessibly', function (string $path): void {
    $this->actingAs($this->root);

    visit($path)
        ->wait(0.6)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['/audit', '/access/users', '/settings/profile', '/settings/preferences', '/notifications']);
