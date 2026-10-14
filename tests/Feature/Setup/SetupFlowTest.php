<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Mail\TestEmail;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Organization\Models\Branch;
use App\Domain\Setup\Actions\IssueOwnerSetupLink;
use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;
use App\Domain\Setup\Support\SetupState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

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

it('saves the first branch in step 4 and edits it on a second save', function (): void {
    seedAccess();
    $root = confirmTwoFactor(userWithRoles('super_admin'));
    $payload = [
        'code' => 'ruh', 'name' => ['en' => 'Riyadh', 'ar' => 'الرياض'], 'countryCode' => 'SA', 'timezone' => 'Asia/Riyadh',
        'defaultCurrency' => 'SAR', 'phoneRegion' => 'SA', 'weekendDays' => [5, 6], 'workStartsAt' => '08:00', 'workEndsAt' => '17:00',
        'geofence' => ['lat' => 24.71, 'lng' => 46.67, 'radiusM' => 200],
    ];

    $this->actingAs($root)->post(route('setup.steps.branch'), [...$payload, 'timezone' => 'Nowhere'])->assertSessionHasErrors('timezone');
    $this->post(route('setup.steps.branch'), $payload)->assertRedirect();
    $this->post(route('setup.steps.branch'), [...$payload, 'code' => 'ruh-hq'])->assertRedirect();

    expect(Branch::query()->sole()->code)->toBe('RUH-HQ')
        ->and(resolve(SetupState::class)->status(SetupStep::Branch))->toBe(StepStatus::Completed);

    $this->get(route('setup.show', 'branch'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('branch.code', 'RUH-HQ')->where('branch.geofence.radiusM', 200));
});

it('stores the email connection in the vault without echoing the password, and sends a test email', function (): void {
    seedAccess();
    Mail::fake();
    $root = confirmTwoFactor(userWithRoles('super_admin'));
    $settings = ['host' => 'smtp.example.com', 'port' => 587, 'security' => 'starttls', 'username' => 'mailer', 'password' => 'smtp-secret-1', 'fromAddress' => 'HR@Example.com', 'fromName' => 'Najd HR'];

    $this->actingAs($root)->post(route('setup.steps.email'), $settings)->assertRedirect();

    $connection = IntegrationConnection::query()->sole();
    expect($connection->credentials)->toBe(['password' => 'smtp-secret-1'])
        ->and(DB::table('integration_connections')->value('credentials'))->not->toContain('smtp-secret-1')
        ->and(resolve(SetupState::class)->status(SetupStep::Email))->toBe(StepStatus::Completed)
        ->and(json_encode(Activity::query()->get()->toArray()))->not->toContain('smtp-secret-1');

    // An empty password keeps the saved one; the page never receives it.
    $this->actingAs($root)->post(route('setup.steps.email'), [...$settings, 'password' => null])->assertRedirect();
    expect($connection->fresh()?->credentials)->toBe(['password' => 'smtp-secret-1']);

    $this->actingAs($root)->get(route('setup.show', ['step' => 'email']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('email.hasPassword', true)->where('email.fromAddress', 'hr@example.com')->missing('email.password'));

    $this->actingAs($root)->post(route('setup.steps.email.test'))->assertRedirect();
    Mail::assertSent(TestEmail::class, fn ($mail): bool => $mail->hasTo($root->email));
    expect($connection->fresh()?->status)->toBe('healthy');
});
