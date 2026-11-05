<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Actions\InviteEmployee;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\UserInvitation;
use App\Domain\People\Notifications\EmployeeInvitationNotification;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => seedAccess());

function tokenFrom(string $url): string
{
    return basename(parse_url($url, PHP_URL_PATH) ?: '');
}

it('invites an employee by email and lets them create a linked account once', function (): void {
    Notification::fake();
    $employee = Employee::factory()->placedIn(Branch::factory()->create())->create(['work_email' => 'Lina@Mawred.test', 'name_en' => 'Lina Odeh']);

    ['url' => $url, 'invitation' => $invitation] = resolve(InviteEmployee::class)->handle(Actor::system('test'), $employee);

    Notification::assertSentTo(new AnonymousNotifiable, EmployeeInvitationNotification::class, fn ($n, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'lina@mawred.test' && $n->url === $url);
    expect($invitation->token_hash)->not->toContain(tokenFrom($url));

    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('auth/invitation')->where('email', 'lina@mawred.test'));
    $this->post($url, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post($url, ['password' => 'A-strong-passphrase-42', 'password_confirmation' => 'A-strong-passphrase-42'])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'lina@mawred.test')->sole();
    $this->assertAuthenticatedAs($user);
    expect($employee->fresh()?->user_id)->toBe($user->id)
        ->and($user->hasRole('employee'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    auth()->logout();
    $this->get($url)->assertNotFound();
});

it('replaces an earlier invitation and refuses duplicates', function (): void {
    Notification::fake();
    $employee = Employee::factory()->create(['work_email' => 'sam@mawred.test']);

    $first = resolve(InviteEmployee::class)->handle(Actor::system('test'), $employee);
    resolve(InviteEmployee::class)->handle(Actor::system('test'), $employee);

    $this->get($first['url'])->assertNotFound();
    expect(UserInvitation::query()->whereNull('revoked_at')->count())->toBe(1);

    User::factory()->create(['email' => 'taken@mawred.test']);
    expect(fn () => resolve(InviteEmployee::class)->handle(Actor::system('test'), $employee, 'TAKEN@mawred.test'))->toThrow(DomainRuleViolation::class)
        ->and(fn () => resolve(InviteEmployee::class)->handle(Actor::system('test'), Employee::factory()->create(['work_email' => null])))->toThrow(DomainRuleViolation::class);
});

it('expires invitations after a week', function (): void {
    Notification::fake();
    ['url' => $url] = resolve(InviteEmployee::class)->handle(Actor::system('test'), Employee::factory()->create());

    $this->travel(8)->days();

    $this->get($url)->assertNotFound();
});

it('hands HR the invitation link once so they can share it themselves', function (): void {
    Notification::fake();
    $employee = Employee::factory()->placedIn(Branch::factory()->create())->create(['work_email' => 'nora@mawred.test']);
    $hr = userWithRoles('hr_admin');
    $hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $response = $this->actingAs($hr)->post(route('people.invitation.store', $employee))->assertRedirect();
    /** @var array{url: string, email: string, expiresAt: string} $link */
    $link = $response->getSession()->get('inertia.flash_data.invitationLink');

    expect($link['email'])->toBe('nora@mawred.test')
        ->and($link['url'])->toContain('/invite/')
        ->and($link['expiresAt'])->toBe(UserInvitation::query()->sole()->expires_at->toIso8601String());
    Notification::assertSentTo(new AnonymousNotifiable, EmployeeInvitationNotification::class, fn ($n): bool => $n->url === $link['url']);

    auth()->logout();
    $this->get($link['url'])->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('auth/invitation'));
});
