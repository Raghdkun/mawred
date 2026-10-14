<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
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
     * @param  array<string, string>  $actions  decision => URL
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly ?string $url,
        public readonly string $recipientLocale,
        public readonly array $actions = [],
        public readonly array $items = [],
    ) {
        $this->onQueue('notifications');
        $this->locale($recipientLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            text: 'mail.notification-text',
            with: [
                'rtl' => $this->recipientLocale === 'ar',
                'header' => $this->recipientLocale === 'ar' ? 'email-header-ar.png' : 'email-header-en.png',
            ],
        );
    }
}
