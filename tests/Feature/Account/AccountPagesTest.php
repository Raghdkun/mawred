<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserSession;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

it('saves display preferences', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('preferences.update'), [
        'locale' => 'ar', 'digits' => 'arab', 'showHijri' => true, 'timezone' => 'Asia/Riyadh',
    ])->assertRedirect();

    expect($user->fresh())
        ->locale->toBe('ar')
        ->digits->toBe('arab')
        ->show_hijri->toBeTrue()
        ->timezone->toBe('Asia/Riyadh');

    $this->patch(route('preferences.update'), ['locale' => 'fr', 'digits' => 'latn', 'showHijri' => false, 'timezone' => 'Mars/Olympus'])
        ->assertSessionHasErrors(['locale', 'timezone']);
});

it('switches language for guests and users', function (): void {
    $this->patch(route('locale.update'), ['locale' => 'ar'])->assertRedirect();
    $this->get(route('login'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('locale', 'ar')->where('dir', 'rtl'));

    $user = User::factory()->create();
    $this->actingAs($user)->patch(route('locale.update'), ['locale' => 'ar']);

    expect($user->fresh()?->locale)->toBe('ar');
});

it("signs out your own sessions but not other people's", function (): void {
    $user = User::factory()->create();
    $mine = UserSession::query()->create(['user_id' => $user->id, 'last_seen_at' => now()]);
    $theirs = UserSession::query()->create(['user_id' => User::factory()->create()->id, 'last_seen_at' => now()]);

    $this->actingAs($user)->delete(route('sessions.destroy', $mine))->assertRedirect();
    $this->delete(route('sessions.destroy', $theirs))->assertNotFound();

    expect($mine->fresh()?->revoked_at)->not->toBeNull()
        ->and($theirs->fresh()?->revoked_at)->toBeNull();
});

it('lists and marks notifications read, only your own', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $make = fn (User $owner) => DatabaseNotification::query()->create([
        'id' => Str::lower((string) Str::ulid()),
        'type' => 'test',
        'notifiable_type' => $owner->getMorphClass(),
        'notifiable_id' => $owner->id,
        'data' => ['category' => 'general', 'title' => 'Hello'],
    ]);
    $mine = $make($user);
    $theirs = $make($other);

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('notifications/index')->has('notifications.data', 1));

    $this->post(route('notifications.read', $theirs))->assertNotFound();
    $this->post(route('notifications.read', $mine))->assertRedirect();

    expect($mine->fresh()?->read_at)->not->toBeNull();

    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('notifications.unread', 0));
});

it('renders illustrated error pages for browsers', function (): void {
    config(['app.debug' => false]);

    $this->actingAs(User::factory()->create())->get('/definitely-missing')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('errors/show')->where('status', 404));
});
