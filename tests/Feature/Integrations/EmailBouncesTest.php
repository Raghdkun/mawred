<?php

declare(strict_types=1);

use App\Domain\Hiring\Actions\SendCandidateMessage;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Models\EmailSuppression;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
| D127: Postmark reports hard bounces and spam complaints to a secret webhook; those addresses
| stop receiving email until an admin allows them again.
*/
beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    $this->admin = userWithRoles('hr_admin');
    $this->admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    IntegrationConnection::query()->create(['type' => 'email', 'name' => 'default', 'status' => 'healthy', 'config' => ['host' => 'smtp.postmarkapp.com', 'port' => 587, 'from_address' => 'hr@example.com']]);
});

it('issues a secret webhook URL and records hard bounces and complaints', function (): void {
    $this->actingAs($this->admin)->post(route('integrations.email.bounces.rotate'))->assertRedirect();
    $this->actingAs($this->admin)->get(route('integrations.email'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('bounces.tokenLast4', fn ($v): bool => is_string($v) && strlen($v) === 4));

    $token = Str::random(48);
    $connection = IntegrationConnection::query()->sole();
    $connection->forceFill(['config' => [...$connection->config, 'bounce_token_hash' => hash('sha256', $token)]])->save();
    $url = route('hooks.email.bounces', ['token' => $token]);

    $this->postJson(route('hooks.email.bounces', ['token' => Str::random(48)]), ['RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'x@example.com'])->assertNotFound();
    $this->postJson($url, ['RecordType' => 'Bounce', 'Type' => 'SoftBounce', 'Email' => 'soft@example.com'])->assertNoContent();
    $this->postJson($url, ['RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'Gone@Example.com', 'Description' => 'The server was unable to deliver your message'])->assertNoContent();
    $this->postJson($url, ['RecordType' => 'SpamComplaint', 'Email' => 'angry@example.com'])->assertNoContent();
    $this->postJson($url, ['RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'gone@example.com'])->assertNoContent();

    expect(EmailSuppression::query()->orderBy('email')->pluck('reason', 'email')->all())
        ->toBe(['angry@example.com' => 'spam_complaint', 'gone@example.com' => 'hard_bounce']);
});

it('stops emailing a suppressed address, and an admin can allow it again', function (): void {
    $user = userWithRoles('employee');
    $user->forceFill(['email' => 'gone@example.com'])->save();
    $suppression = EmailSuppression::query()->create(['email' => 'gone@example.com', 'reason' => 'hard_bounce', 'source' => 'postmark']);
    $message = fn (string $key): NotificationMessage => new NotificationMessage('test.event', NotificationCategory::Requests, fn (): array => ['subject' => 'Hi', 'body' => 'Body'], null, $key, ['email']);

    resolve(NotificationRouter::class)->send($user, $message('one'));
    Mail::assertNothingQueued();

    $candidate = Candidate::factory()->create(['email' => 'gone@example.com']);
    expect(fn () => resolve(SendCandidateMessage::class)->handle(Actor::user($this->admin), $candidate, null, MessageChannel::Email, 'Hello', 'Hi', MessagePurpose::Manual))
        ->toThrow(DomainRuleViolation::class, 'bounced');

    $this->actingAs($this->admin)->delete(route('integrations.email.suppressions.destroy', $suppression))->assertRedirect();
    resolve(NotificationRouter::class)->send($user, $message('two'));
    Mail::assertQueuedCount(1);
});
