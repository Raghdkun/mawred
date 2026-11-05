<?php

declare(strict_types=1);

use App\Domain\Access\Actions\IssuePasswordSetupLink;
use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

/*
| D136: `mawred:create-admin` makes a staff account from the server's shell and prints a one-time
| password link (the "Forgot password" broker), so nobody else ever knows their password.
*/

it('creates a Super Admin by default whose password link works once', function (): void {
    seedAccess();

    $this->artisan('mawred:create-admin', ['--name' => 'Huda Admin', '--email' => 'Huda@Example.com'])
        ->expectsOutputToContain('reset-password/')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'huda@example.com')->sole();
    expect($admin->status)->toBe('active')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and(Activity::query()->where('event', 'roles.assigned')->where('subject_id', $admin->id)->exists())->toBeTrue();

    // The command's link is the same kind it prints: open it, choose a password, sign in with it.
    $url = resolve(IssuePasswordSetupLink::class)->handle($admin);
    $token = basename(parse_url($url, PHP_URL_PATH) ?: '');
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('auth/reset-password'));

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'huda@example.com',
        'password' => 'A-strong-passphrase-42',
        'password_confirmation' => 'A-strong-passphrase-42',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('A-strong-passphrase-42', (string) $admin->fresh()?->password))->toBeTrue();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'huda@example.com',
        'password' => 'Another-passphrase-99',
        'password_confirmation' => 'Another-passphrase-99',
    ])->assertSessionHasErrors('email');
});

it('gives the chosen role and refuses unknown, internal and taken ones', function (): void {
    seedAccess();

    $this->artisan('mawred:create-admin', ['--name' => 'Omar HR', '--email' => 'omar@example.com', '--role' => 'hr_admin'])->assertSuccessful();
    expect(User::query()->where('email', 'omar@example.com')->sole()->getRoleNames()->all())->toBe(['hr_admin']);

    $this->artisan('mawred:create-admin', ['--name' => 'X', '--email' => 'x@example.com', '--role' => 'wizard'])->assertFailed();
    $this->artisan('mawred:create-admin', ['--name' => 'Bot', '--email' => 'bot@example.com', '--role' => 'integration_bot'])->assertFailed();
    $this->artisan('mawred:create-admin', ['--name' => 'Again', '--email' => 'OMAR@example.com'])->assertFailed();

    expect(User::query()->whereIn('email', ['x@example.com', 'bot@example.com'])->exists())->toBeFalse()
        ->and(User::query()->where('email', 'omar@example.com')->count())->toBe(1);
});

it('reissues a link for an existing account and needs mawred:install first', function (): void {
    $this->artisan('mawred:create-admin', ['--name' => 'Early', '--email' => 'early@example.com'])
        ->expectsOutputToContain('mawred:install')
        ->assertFailed();

    seedAccess();
    $this->artisan('mawred:create-admin', ['--name' => 'Lina', '--email' => 'lina@example.com'])->assertSuccessful();

    $this->artisan('mawred:create-admin', ['--relink' => true, '--email' => 'Lina@example.com'])
        ->expectsOutputToContain('reset-password/')
        ->assertSuccessful();
    $this->artisan('mawred:create-admin', ['--relink' => true, '--email' => 'nobody@example.com'])->assertFailed();
});
