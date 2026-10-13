<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;

/*
| Critical journeys in EN and AR (brief §20), with axe accessibility checks (WCAG 2.2 AA).
*/

beforeEach(fn () => seedAccess());

it('renders the sign-in page accessibly in both languages', function (string $locale, string $dir, string $heading): void {
    $this->withSession(['locale' => $locale]);

    visit('/login')
        ->assertSee($heading)
        ->assertScript('document.documentElement.dir', $dir)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with([
    'English' => ['en', 'ltr', 'Welcome back'],
    'Arabic' => ['ar', 'rtl', 'مرحبًا بعودتك'],
]);

it('signs an employee in and lands on home', function (): void {
    $user = userWithRoles('employee');

    visit('/login')
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Sign in')
        ->assertPathIs('/dashboard')
        ->assertSee('Your agenda')
        ->wait(0.6)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('switches to Arabic without a full reload and keeps RTL after navigation', function (): void {
    $user = userWithRoles('employee');
    $user->forceFill(['locale' => 'en'])->save();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertScript('document.documentElement.dir', 'ltr')
        ->click('@language-toggle')
        ->wait(1)
        ->assertScript('document.documentElement.dir', 'rtl')
        ->assertSee('جدولك')
        ->assertNoJavaScriptErrors();

    expect(User::query()->find($user->id)?->locale)->toBe('ar');
});

it('shows the illustrated 404 page', function (): void {
    config(['app.debug' => false]);
    $this->actingAs(userWithRoles('employee'));

    visit('/no-such-page')
        ->assertSee('Page not found')
        ->wait(0.6)
        ->assertNoAccessibilityIssues();
});
