<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The "send a test email" message from the email step: proves the connection end to end. */
final class TestEmail extends Mailable
{
    public function __construct(public readonly string $recipientLocale)
    {
        $this->locale($recipientLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Mawred can send email', [], $this->recipientLocale));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            text: 'mail.notification-text',
            with: [
                'subjectLine' => __('Mawred can send email', [], $this->recipientLocale),
                'bodyText' => __('This test message arrived, so notifications and approval emails will too.', [], $this->recipientLocale),
                'url' => null,
                'actions' => [],
                'items' => [],
                'rtl' => $this->recipientLocale === 'ar',
                'header' => $this->recipientLocale === 'ar' ? 'email-header-ar.png' : 'email-header-en.png',
            ],
        );
    }
}
