<?php

declare(strict_types=1);

use App\Domain\Access\Actions\RevokeUserSession;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserSession;
use App\Domain\Audit\Models\Activity;
use App\Support\Actors\Actor;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => seedAccess());

describe('sign-in', function (): void {
    it('records successful and failed sign-ins in the auth log', function (): void {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $events = Activity::query()->where('log_name', 'auth')->pluck('event')->all();

        expect($events)->toContain('login.failed')->toContain('login')
            ->and(json_encode(Activity::query()->where('log_name', 'auth')->get()))->not->toContain('wrong');
    });

    it('refuses web sign-in for suspended and API-only accounts', function (string $case): void {
        $user = $case === 'suspended'
            ? User::factory()->create(['status' => 'suspended'])
            : userWithRoles('integration_bot');

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    })->with(['suspended', 'api-only']);
});

describe('active sessions', function (): void {
    it('tracks a session per sign-in and signs out revoked sessions', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $session = UserSession::query()->where('user_id', $user->id)->sole();

        resolve(RevokeUserSession::class)->handle(Actor::user($user), $session);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('marks the session revoked on logout', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'));
        $this->post(route('logout'));

        expect(UserSession::query()->where('user_id', $user->id)->sole()->revoked_at)->not->toBeNull();
    });
});

describe('two-factor by role', function (): void {
    it('sends users whose role requires 2FA to enrolment', function (): void {
        $officer = userWithRoles('hr_officer');

        $this->actingAs($officer)->get(route('dashboard'))->assertRedirect(route('security.edit'));
    });

    it('lets them through once 2FA is confirmed', function (): void {
        $officer = userWithRoles('hr_officer');
        $officer->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($officer)->get(route('dashboard'))->assertOk();
    });

    it('does not affect roles without the requirement', function (): void {
        $this->actingAs(userWithRoles('employee'))->get(route('dashboard'))->assertOk();
    });

    it('answers API calls with a 403 envelope', function (): void {
        Route::middleware(['api', 'auth:sanctum'])->get('api/v1/_test/ping', fn (): string => 'pong');

        $this->actingAs(userWithRoles('hr_officer'), 'sanctum')
            ->getJson('/api/v1/_test/ping')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'access.two_factor_required');
    });
});

describe('impersonation', function (): void {
    beforeEach(function (): void {
        $this->root = userWithRoles('super_admin');
        $this->root->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    });

    it('lets a Super Admin act as another user and records who really acted', function (): void {
        $employee = userWithRoles('employee');

        // A real sign-in stores the account id under the guard's session key; actingAs() does not.
        $this->actingAs($this->root)
            ->withSession([auth()->guard('web')->getName() => $this->root->id])
            ->post(route('access.impersonate.start', $employee))
            ->assertRedirect(route('home'));

        $this->get(route('dashboard'))->assertOk();
        expect(auth()->id())->toBe($employee->id);

        $this->patch(route('profile.update'), ['name' => 'Changed while impersonated', 'email' => $employee->email]);

        $entry = Activity::query()->where('subject_type', $employee->getMorphClass())->where('subject_id', $employee->id)->where('event', 'updated')->latest('id')->first();
        expect($entry?->impersonator_id)->toBe($this->root->id)
            ->and($entry?->causer_id)->toBe($employee->id);

        $this->delete(route('access.impersonate.stop'))->assertRedirect(route('home'));
        $this->get(route('dashboard'));
        expect(auth()->id())->toBe($this->root->id);

        expect(Activity::query()->where('log_name', 'auth')->pluck('event')->all())
            ->toContain('impersonation.started')->toContain('impersonation.stopped');
    });

    it('never impersonates another Super Admin, yourself, or as a non-Super Admin', function (): void {
        $otherRoot = userWithRoles('super_admin');
        $hrAdmin = userWithRoles('hr_admin');
        $hrAdmin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($this->root)->post(route('access.impersonate.start', $otherRoot))->assertForbidden();
        $this->actingAs($this->root)->post(route('access.impersonate.start', $this->root))->assertForbidden();
        $this->actingAs($hrAdmin)->post(route('access.impersonate.start', userWithRoles('employee')))->assertForbidden();
    });

    it('returns 404 for malformed user ids', function (): void {
        $this->actingAs($this->root)->post('/access/users/not-a-ulid/impersonate')->assertNotFound();
    });
});
