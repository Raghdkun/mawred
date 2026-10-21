<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Models\PushSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends one notification to every device a person turned push on for (D128). Devices the push
 * service no longer knows (gone, unsubscribed) are forgotten.
 */
#[Queue('notifications')]
#[Tries(3)]
#[Backoff([30, 120])]
final class SendWebPushJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{title: string, body: string|null, url: string|null, tag: string}  $payload
     */
    public function __construct(public readonly int $userId, public readonly array $payload) {}

    public function handle(): void
    {
        $keys = config('services.webpush');

        if (! is_array($keys) || ! is_string($keys['public_key'] ?? null) || ! is_string($keys['private_key'] ?? null) || $keys['public_key'] === '') {
            return;
        }

        $subscriptions = PushSubscription::query()->where('user_id', $this->userId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $push = new WebPush(['VAPID' => [
            'subject' => is_string($keys['subject'] ?? null) ? $keys['subject'] : 'mailto:hr@example.com',
            'publicKey' => $keys['public_key'],
            'privateKey' => $keys['private_key'],
        ]], ['TTL' => 86400, 'urgency' => 'normal', 'topic' => substr(hash('sha256', $this->payload['tag']), 0, 32)]);
        $body = (string) json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription;
            $push->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $body);
        }

        foreach ($push->flush() as $report) {
            if (! $report instanceof MessageSentReport) {
                continue;
            }

            $subscription = $byEndpoint[$report->getEndpoint()] ?? null;

            if (! $subscription instanceof PushSubscription) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $subscription->delete();
            } elseif ($report->isSuccess()) {
                $subscription->forceFill(['last_used_at' => CarbonImmutable::now()])->save();
            }
        }
    }
}
