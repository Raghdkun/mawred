<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WhatsApp;

/**
 * What to send. Drivers pick what they can: Official uses the template outside the 24-hour
 * window (with quick-reply payloads) and text or buttons inside it; WAHA always sends text.
 */
final readonly class OutgoingMessage
{
    /**
     * @param  array{name: string, language: string, params: list<string>, buttons: list<string>}|null  $template
     * @param  list<array{id: string, title: string}>  $buttons  interactive replies (24 h window only)
     */
    public function __construct(
        public string $to,
        public string $text,
        public ?array $template = null,
        public array $buttons = [],
        public bool $inServiceWindow = false,
    ) {}

    /** WhatsApp chat id for a number: digits only. */
    public function digits(): string
    {
        return (string) preg_replace('/\D+/', '', $this->to);
    }
}
