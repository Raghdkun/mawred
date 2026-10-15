<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Models\NotificationTemplate;
use App\Support\Templates\SafeTemplate;

/**
 * Renders a catalog event for a channel and locale: an admin override when one exists, else the
 * built-in default, else English. Output is plain text; the channel escapes it. WhatsApp text
 * falls back to the in-app wording, and may carry the approved Meta template mapped to it
 * (name, language, the variables filling {{1}}, {{2}}… and the quick-reply decisions).
 */
final readonly class TemplateRenderer
{
    public function __construct(private SafeTemplate $templates) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array{subject: string, body: string, template?: array{name: string, language: string, params: list<string>, buttons: list<string>}|null}
     */
    public function render(string $event, string $channel, string $locale, array $variables): array
    {
        $template = $this->template($event, $channel, $locale);
        $rendered = [
            'subject' => $this->templates->render($template['subject'] ?? '', $variables, $locale, escapeHtml: false),
            'body' => trim($this->templates->render($template['body'], $variables, $locale, escapeHtml: false)),
        ];

        if ($channel === 'whatsapp') {
            $rendered['template'] = $this->metaTemplate($event, $locale, $variables);
        }

        return $rendered;
    }

    /**
     * @return array{subject?: string|null, body: string}
     */
    public function template(string $event, string $channel, string $locale): array
    {
        $override = NotificationTemplate::query()->where(['event' => $event, 'channel' => $channel, 'locale' => $locale])->first();

        if ($override instanceof NotificationTemplate && trim($override->body) !== '') {
            return ['subject' => $override->subject, 'body' => $override->body];
        }

        $defaults = NotificationCatalog::event($event)['templates'][$channel === 'whatsapp' ? 'in_app' : $channel] ?? [];

        return $defaults[$locale] ?? $defaults['en'] ?? ['body' => ''];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array{name: string, language: string, params: list<string>, buttons: list<string>}|null
     */
    private function metaTemplate(string $event, string $locale, array $variables): ?array
    {
        $mapping = NotificationTemplate::query()->where(['event' => $event, 'channel' => 'whatsapp', 'locale' => $locale])->value('wa_template');
        $mapping = is_string($mapping) ? json_decode($mapping, true) : $mapping;

        if (! is_array($mapping) || ! is_string($mapping['name'] ?? null) || $mapping['name'] === '') {
            return null;
        }

        $params = [];

        foreach (is_array($mapping['params'] ?? null) ? $mapping['params'] : [] as $variable) {
            $params[] = is_string($variable) ? $this->templates->render('{{ '.$variable.' }}', $variables, $locale, escapeHtml: false) : '';
        }

        $buttons = array_values(array_filter(is_array($mapping['buttons'] ?? null) ? $mapping['buttons'] : [], fn (mixed $decision): bool => in_array($decision, ['approved', 'rejected'], true)));

        return [
            'name' => $mapping['name'],
            'language' => is_string($mapping['language'] ?? null) ? $mapping['language'] : $locale,
            'params' => $params,
            'buttons' => $buttons,
        ];
    }
}
