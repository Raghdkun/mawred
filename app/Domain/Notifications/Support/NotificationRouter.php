<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Integrations\WhatsApp\OutgoingMessage;
use App\Domain\Integrations\WhatsApp\WhatsAppGateway;
use App\Domain\Integrations\WhatsApp\WhatsAppRouting;
use App\Domain\Notifications\Mail\NotificationMail;
use App\Domain\Notifications\Notifications\InAppNotification;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers one message to one person over the channels they allow (ADR-0016). In-app always
 * lands immediately. Outside the app, the category's chain is tried in order (WhatsApp, then
 * email by default, D59); email waits out quiet hours or joins the daily digest unless the
 * category is critical. Every attempt is logged, and a repeated cause is skipped.
 */
final readonly class NotificationRouter
{
    public function __construct(
        private NotificationPreferences $preferences,
        private DeliveryLog $log,
        private WhatsAppRouting $routing,
        private WhatsAppGateway $whatsApp,
    ) {}

    public function send(User $user, NotificationMessage $message): void
    {
        if ($user->status !== 'active' || $user->trashed()) {
            return;
        }

        $locale = in_array($user->locale, ['en', 'ar'], true) ? $user->locale : 'en';
        $outside = false;

        foreach ($message->channels as $channel) {
            if ($channel === 'in_app') {
                $this->attempt($user, $message, 'in_app', fn (): string => $this->inApp($user, $message, $locale));

                continue;
            }

            // Email and WhatsApp are one delivery "outside the app", tried along the category's chain (D59).
            if (! $outside) {
                $outside = true;
                $this->outside($user, $message, $locale);
            }
        }
    }

    private function outside(User $user, NotificationMessage $message, string $locale): void
    {
        foreach ($this->routing->chain($message->category) as $step) {
            $status = $step === 'email'
                ? $this->attempt($user, $message, 'email', fn (): string => $this->email($user, $message, $locale))
                : $this->attempt($user, $message, 'whatsapp', fn (): string => $this->whatsApp($user, $message, $locale, IntegrationType::from($step)));

            if (in_array($status, ['sent', 'queued', 'deferred', 'digest', 'seen'], true)) {
                return;
            }
        }
    }

    /**
     * Runs one channel once per cause (idempotency key), honouring the person's switches.
     *
     * @param  Closure(): string  $deliver
     */
    private function attempt(User $user, NotificationMessage $message, string $channel, Closure $deliver): string
    {
        $key = "{$message->event}:{$message->sourceKey}:{$user->id}:{$channel}";

        if ($this->log->seen($key)) {
            return 'seen';
        }

        if (! $this->preferences->enabled($user, $message->category, $channel)) {
            $this->log->record($user->id, $message->event, $message->category->value, $channel, 'disabled', $key);

            return 'disabled';
        }

        try {
            $status = $deliver();
        } catch (Throwable $e) {
            report($e);
            $status = 'failed';
        }

        // A skipped WhatsApp step (no verified number, quiet hours…) leaves no record, so a later
        // retry of the same cause can still use it.
        if ($status !== 'skipped') {
            $this->log->record($user->id, $message->event, $message->category->value, $channel, $status, $status === 'failed' ? null : $key);
        }

        return $status;
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
        $actions = $message->actions instanceof Closure ? ($message->actions)('email') : [];
        $mail = new NotificationMail($content['subject'], $content['body'], $message->url, $locale, $actions, ics: $message->ics instanceof Closure ? ($message->ics)() : null);
        $quietUntil = $critical ? null : $this->preferences->quietUntil($user, CarbonImmutable::now());

        if ($quietUntil instanceof CarbonImmutable) {
            Mail::to($user)->queue($mail->delay($quietUntil));

            return 'deferred';
        }

        Mail::to($user)->queue($mail);

        return 'queued';
    }

    /**
     * One WhatsApp step (ADR-0015): verified numbers only (D58); non-urgent messages wait for
     * email during quiet hours or the digest; approvals get one-tap buttons on Official and
     * signed links on the Unofficial driver.
     */
    private function whatsApp(User $user, NotificationMessage $message, string $locale, IntegrationType $driver): string
    {
        $contact = WhatsAppContact::query()->where('user_id', $user->id)->whereNotNull('verified_at')->first();
        $critical = $message->category->isCritical();

        if (! $contact instanceof WhatsAppContact || ! WhatsAppGateway::usable($driver) instanceof IntegrationConnection) {
            return 'skipped';
        }

        if (! $critical && ($user->digest_enabled || $this->preferences->quietUntil($user, CarbonImmutable::now()) instanceof CarbonImmutable)) {
            return 'skipped';
        }

        $content = ($message->content)('whatsapp', $locale);
        $links = $message->actions instanceof Closure ? ($message->actions)('whatsapp') : [];
        $quick = $links !== [] && $driver === IntegrationType::WhatsAppOfficial;
        $text = trim('*'.$content['subject']."*\n\n".$content['body']);

        if (! $quick && $links !== []) {
            $text .= "\n";

            foreach ($links as $action => $link) {
                $text .= "\n".ActionLabels::label($action, $locale).': '.$link;
            }
        } elseif ($message->url !== null) {
            $text .= "\n\n".$message->url;
        }

        $template = $content['template'] ?? null;

        if ($template !== null && $quick) {
            $template['buttons'] = array_values(array_filter(array_map(fn (string $decision): ?string => isset($links[$decision]) ? 'mawred:'.basename((string) parse_url($links[$decision], PHP_URL_PATH)) : null, $template['buttons'])));
        } elseif ($template !== null) {
            $template['buttons'] = [];
        }

        // WhatsApp reply buttons: at most three, each carrying the action link's token.
        $buttons = [];

        foreach ($quick ? array_slice($links, 0, 3, true) : [] as $action => $link) {
            $buttons[] = ['id' => 'mawred:'.basename((string) parse_url($link, PHP_URL_PATH)), 'title' => ActionLabels::label($action, $locale)];
        }

        $result = $this->whatsApp->send($driver, $user, $message->event, new OutgoingMessage($contact->phone_e164, $text, $template, $buttons, $contact->inServiceWindow()));

        return $result->sent ? 'sent' : 'failed';
    }
}
