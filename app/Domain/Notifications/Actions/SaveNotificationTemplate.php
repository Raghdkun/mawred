<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Support\NotificationCatalog;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Templates\SafeTemplate;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Saves an admin's wording for one event and channel, in both languages (handoff 42). Only the
 * event's allowed variables pass, and text is stored as written: SafeTemplate renders it later.
 */
final readonly class SaveNotificationTemplate
{
    public function __construct(private ConnectionInterface $db, private SafeTemplate $templates) {}

    /**
     * @param  array<string, array{subject?: string|null, body: string}>  $locales  locale => text
     */
    public function handle(User $actor, string $event, string $channel, array $locales): void
    {
        $definition = NotificationCatalog::event($event);

        if ($definition === null || ! isset($definition['templates'][$channel])) {
            throw new DomainRuleViolation('notifications.unknown_template', __('This notification can’t be edited.'), [], 404);
        }

        $errors = [];

        foreach ($locales as $locale => $text) {
            foreach (['subject' => $text['subject'] ?? '', 'body' => $text['body']] as $part => $template) {
                try {
                    $this->templates->assertValid((string) $template, $definition['variables']);
                } catch (InvalidArgumentException) {
                    $errors["{$locale}.{$part}"] = [__('Use only the variables listed for this notification.')];
                }
            }
        }

        if ($errors !== []) {
            throw new DomainRuleViolation('notifications.bad_variables', __('Use only the variables listed for this notification.'), $errors);
        }

        $this->db->transaction(function () use ($actor, $event, $channel, $locales): void {
            foreach ($locales as $locale => $text) {
                NotificationTemplate::query()->updateOrCreate(
                    ['event' => $event, 'channel' => $channel, 'locale' => $locale],
                    ['subject' => $text['subject'] ?? null, 'body' => $text['body'], 'updated_by' => $actor->id],
                );
            }

            activity('notifications')->causedBy($actor)->event('template.saved')->withProperties(['event' => $event, 'channel' => $channel])->log('Notification template saved');
        });
    }
}
