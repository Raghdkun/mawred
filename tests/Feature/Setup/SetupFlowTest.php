<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Setup\Actions\IssueOwnerSetupLink;
use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;
use App\Domain\Setup\Support\SetupState;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->markSetupIncomplete());

function confirmTwoFactor(User $user): User
{
    $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

it('installs the owner and prints a one-time setup link', function (): void {
    $this->artisan('mawred:install', ['--name' => 'Sara Admin', '--email' => 'Sara@Example.com'])
        ->expectsOutputToContain('setup/claim/')
        ->assertSuccessful();

    $owner = User::query()->where('email', 'sara@example.com')->sole();

    expect($owner->status)->toBe('invited')
        ->and($owner->hasRole('super_admin'))->toBeTrue();

    $this->artisan('mawred:install', ['--name' => 'Second', '--email' => 'b@example.com'])->assertFailed();
});

it('lets the owner claim the account once, then requires 2FA before the wizard', function (): void {
    $this->artisan('mawred:install', ['--name' => 'Sara', '--email' => 'sara@example.com']);
    $owner = User::query()->where('email', 'sara@example.com')->sole();
    $url = resolve(IssueOwnerSetupLink::class)->handle($owner);
    $token = basename(parse_url($url, PHP_URL_PATH) ?: '');

    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('setup/claim')->where('email', 'sara@example.com'));

    $this->post(route('setup.claim.store', $token), ['password' => 'a-Strong-pass-123!', 'password_confirmation' => 'a-Strong-pass-123!'])
        ->assertRedirect(route('setup.show'));

    $this->assertAuthenticatedAs($owner);
    expect($owner->fresh()?->status)->toBe('active');

    // Super Admin requires 2FA → enrolment first.
    $this->get(route('setup.show'))->assertRedirect(route('security.edit'));

    // The link is single-use.
    auth()->logout();
    $this->get($url)->assertNotFound();
});

it('sends admins to the wizard and everyone else to the being-set-up page', function (): void {
    seedAccess();
    $root = confirmTwoFactor(userWithRoles('super_admin'));
    $employee = userWithRoles('employee');

    $this->actingAs($root)->get(route('dashboard'))->assertRedirect(route('setup.show'));
    $this->actingAs($employee)->get(route('dashboard'))->assertStatus(503)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('setup/pending'));
});

it('is resumable: completing and skipping steps moves to the next pending step', function (): void {
    seedAccess();
    $root = confirmTwoFactor(userWithRoles('super_admin'));

    $this->actingAs($root)->get(route('setup.show'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('setup/wizard')->where('current', 'welcome'));

    $this->post(route('setup.steps.complete', 'welcome'))->assertRedirect(route('setup.show', ['step' => 'company']));
    $this->post(route('setup.steps.skip', 'languages'));

    $state = resolve(SetupState::class);
    expect($state->status(SetupStep::Welcome))->toBe(StepStatus::Completed)
        ->and($state->status(SetupStep::Languages))->toBe(StepStatus::Skipped)
        ->and($state->status(SetupStep::Admin))->toBe(StepStatus::Completed); // satisfied by confirmed 2FA
});

it('refuses to skip required steps or finish before they are done', function (): void {
    seedAccess();
    $root = confirmTwoFactor(userWithRoles('super_admin'));

    $this->actingAs($root)->post(route('setup.steps.skip', 'company'))->assertSessionHasErrors();
    $this->post(route('setup.finish'))->assertSessionHasErrors();

    $this->post(route('setup.steps.complete', 'company'));
    $this->get(route('setup.show'));
    $this->post(route('setup.finish'))->assertRedirect(route('setup.show', ['step' => 'review']));

    expect(resolve(SetupState::class)->isComplete())->toBeTrue();
    $this->get(route('dashboard'))->assertOk();
});

it('forbids the wizard to non-admins', function (): void {
    seedAccess();

    $this->actingAs(userWithRoles('employee'))->get(route('setup.show'))->assertForbidden();
});
