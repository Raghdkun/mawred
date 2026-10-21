<?php

declare(strict_types=1);

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Jobs\SendWebPushJob;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationPreferences;
use App\Domain\Notifications\Support\NotificationRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
| D128: people turn push on per device; each in-app notice is also pushed to their devices,
| following the same per-category switches.
*/
beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
    seedAccess();
    config(['services.webpush.public_key' => 'BPublicKeyForTests', 'services.webpush.private_key' => 'private']);
    $this->user = userWithRoles('employee');
});

function pushSubscribe(object $test, string $endpoint = 'https://push.example.com/send/abc'): void
{
    $test->actingAs($test->user)->postJson(route('notifications.push.subscribe'), [
        'endpoint' => $endpoint, 'keys' => ['p256dh' => 'BKey', 'auth' => 'secret-auth'], 'contentEncoding' => 'aes128gcm',
    ])->assertNoContent();
}

function pushMessage(string $key): NotificationMessage
{
    return new NotificationMessage('requests.decided', NotificationCategory::Requests, fn (): array => ['subject' => 'Your leave was approved', 'body' => 'Enjoy'], 'https://hr.example.com/requests/1', $key, ['in_app']);
}

it('turns push on and off per device, keeping the keys encrypted', function (): void {
    pushSubscribe($this);
    pushSubscribe($this);
    pushSubscribe($this, 'https://push.example.com/send/def');

    expect(PushSubscription::query()->where('user_id', $this->user->id)->count())->toBe(2)
        ->and(DB::table('push_subscriptions')->value('auth_token'))->not->toBe('secret-auth')
        ->and(PushSubscription::query()->first()?->auth_token)->toBe('secret-auth');

    $this->actingAs($this->user)->get(route('notifications.preferences'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('push.publicKey', 'BPublicKeyForTests')->where('push.devices', 2));

    // Someone else can't turn your device off.
    $this->actingAs(userWithRoles('employee'))->postJson(route('notifications.push.unsubscribe'), ['endpoint' => 'https://push.example.com/send/abc'])->assertNoContent();
    expect(PushSubscription::query()->count())->toBe(2);

    $this->actingAs($this->user)->postJson(route('notifications.push.unsubscribe'), ['endpoint' => 'https://push.example.com/send/abc'])->assertNoContent();
    expect(PushSubscription::query()->count())->toBe(1);

    $this->actingAs($this->user)->postJson(route('notifications.push.subscribe'), ['endpoint' => 'http://insecure.example.com', 'keys' => ['p256dh' => 'x', 'auth' => 'y']])->assertUnprocessable();
});

it('pushes in-app notices to the person\'s devices, following their switches', function (): void {
    resolve(NotificationRouter::class)->send($this->user, pushMessage('before'));
    Queue::assertNotPushed(SendWebPushJob::class);

    pushSubscribe($this);
    resolve(NotificationRouter::class)->send($this->user, pushMessage('one'));
    resolve(NotificationRouter::class)->send($this->user, pushMessage('one'));

    Queue::assertPushed(SendWebPushJob::class, 1);
    Queue::assertPushed(SendWebPushJob::class, fn (SendWebPushJob $job): bool => $job->userId === $this->user->id
        && $job->payload['title'] === 'Your leave was approved' && $job->payload['url'] === 'https://hr.example.com/requests/1');

    NotificationPreference::query()->create(['user_id' => $this->user->id, 'category' => 'requests', 'channel' => 'push', 'enabled' => false]);
    app()->forgetScopedInstances();
    app()->forgetInstance(NotificationPreferences::class);
    resolve(NotificationRouter::class)->send($this->user, pushMessage('two'));
    Queue::assertPushed(SendWebPushJob::class, 1);
});

it('prints a VAPID key pair', function (): void {
    $this->artisan('mawred:vapid-keys')->expectsOutputToContain('VAPID_PUBLIC_KEY=')->assertSuccessful();
});
