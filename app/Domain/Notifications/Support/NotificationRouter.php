<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Mail\NotificationMail;
use App\Domain\Notifications\Notifications\InAppNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers one message to one person over the channels they allow (ADR-0016). In-app always
 * lands immediately; email waits out quiet hours, or joins the daily digest, unless the
 * category is critical. Every attempt is logged, and a repeated cause is skipped.
 */
final readonly class NotificationRouter
{
    public function __construct(
        private NotificationPreferences $preferences,
        private DeliveryLog $log,
    ) {}

    public function send(User $user, NotificationMessage $message): void
    {
        if ($user->status !== 'active' || $user->trashed()) {
            return;
        }

        $locale = in_array($user->locale, ['en', 'ar'], true) ? $user->locale : 'en';

        foreach ($message->channels as $channel) {
            $key = "{$message->event}:{$message->sourceKey}:{$user->id}:{$channel}";

            if ($this->log->seen($key)) {
                continue;
            }

            if (! $this->preferences->enabled($user, $message->category, $channel)) {
                $this->log->record($user->id, $message->event, $message->category->value, $channel, 'disabled', $key);

                continue;
            }

            try {
                $status = match ($channel) {
                    'in_app' => $this->inApp($user, $message, $locale),
                    'email' => $this->email($user, $message, $locale),
                    default => 'unsupported',
                };
                $this->log->record($user->id, $message->event, $message->category->value, $channel, $status, $key);
            } catch (Throwable $e) {
                report($e);
                $this->log->record($user->id, $message->event, $message->category->value, $channel, 'failed', $key, $e::class);
            }
        }
    }

    private function inApp(User $user, NotificationMessage $message, string $locale): string
    {
        $content = ($message->content)('in_app', $locale);
        $user->notify(new InAppNotification($message->event, $message->category->value, $content['subject'], $content['body'] === '' ? null : $content['body'], $message->url));

        return 'sent';
    }

    private function email(User $user, NotificationMessage $message, string $locale): string
    {
        $critical = $message->category->isCritical();

        if (! $critical && $user->digest_enabled) {
            return 'digest';
        }

        $content = ($message->content)('email', $locale);
        $actions = $message->actions instanceof \Closure ? ($message->actions)() : [];
        $mail = new NotificationMail($content['subject'], $content['body'], $message->url, $locale, $actions);
        $quietUntil = $critical ? null : $this->preferences->quietUntil($user, CarbonImmutable::now());

        if ($quietUntil instanceof CarbonImmutable) {
            Mail::to($user)->queue($mail->delay($quietUntil));

            return 'deferred';
        }

        Mail::to($user)->queue($mail);

        return 'queued';
    }
}
