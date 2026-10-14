<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Models\NotificationTemplate;
use App\Support\Templates\SafeTemplate;

/**
 * Renders a catalog event for a channel and locale: an admin override when one exists, else the
 * built-in default, else English. Output is plain text; the channel escapes it.
 */
final readonly class TemplateRenderer
{
    public function __construct(private SafeTemplate $templates) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array{subject: string, body: string}
     */
    public function render(string $event, string $channel, string $locale, array $variables): array
    {
        $template = $this->template($event, $channel, $locale);

        return [
            'subject' => $this->templates->render($template['subject'] ?? '', $variables, $locale, escapeHtml: false),
            'body' => trim($this->templates->render($template['body'], $variables, $locale, escapeHtml: false)),
        ];
    }

    /**
     * @return array{subject?: string|null, body: string}
     */
    public function template(string $event, string $channel, string $locale): array
    {
        $override = NotificationTemplate::query()->where(['event' => $event, 'channel' => $channel, 'locale' => $locale])->first();

        if ($override instanceof NotificationTemplate) {
            return ['subject' => $override->subject, 'body' => $override->body];
        }

        $defaults = NotificationCatalog::event($event)['templates'][$channel] ?? [];

        return $defaults[$locale] ?? $defaults['en'] ?? ['body' => ''];
    }
}
