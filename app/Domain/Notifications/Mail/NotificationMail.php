<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail;

use App\Domain\Notifications\Support\ActionLabels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A templated email in the recipient's language and direction. The body is plain text rendered
 * by SafeTemplate; the Blade layout (code, never DB content) escapes it.
 */
final class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{title: string, body: string|null, url: string|null}>  $items  digest entries
     * @param  array<string, string>  $actions  action => URL (the first is the primary button)
     * @param  string|null  $ics  a calendar invitation to attach (D75)
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly ?string $url,
        public readonly string $recipientLocale,
        public readonly array $actions = [],
        public readonly array $items = [],
        public readonly ?string $ics = null,
    ) {
        $this->onQueue('notifications');
        $this->locale($recipientLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $ics = $this->ics;

        return $ics === null ? [] : [Attachment::fromData(fn (): string => $ics, 'invite.ics')->withMime('text/calendar; charset=utf-8; method=REQUEST')];
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            text: 'mail.notification-text',
            with: [
                'rtl' => $this->recipientLocale === 'ar',
                'header' => $this->recipientLocale === 'ar' ? 'email-header-ar.png' : 'email-header-en.png',
                'buttons' => array_map(fn (string $action, string $link): array => ['label' => ActionLabels::label($action, $this->recipientLocale), 'url' => $link], array_keys($this->actions), $this->actions),
            ],
        );
    }
}
