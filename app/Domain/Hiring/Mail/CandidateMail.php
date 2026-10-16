<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An email to a candidate (D94): a co-brand area, so the company leads (D22). The body is plain
 * text; every email ends with the link to have their details deleted (D86).
 */
final class CandidateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{label: string, url: string}>  $buttons
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly string $recipientLocale,
        public readonly string $company,
        public readonly string $privacyUrl,
        public readonly array $buttons = [],
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

        return $ics === null ? [] : [Attachment::fromData(fn (): string => $ics, 'interview.ics')->withMime('text/calendar; charset=utf-8; method=REQUEST')];
    }

    public function content(): Content
    {
        return new Content(view: 'mail.candidate', text: 'mail.candidate-text', with: ['rtl' => $this->recipientLocale === 'ar']);
    }
}
