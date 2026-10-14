<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Enums\NotificationCategory;
use Closure;

/**
 * What to tell one person, independent of channel. Content is produced per channel and locale
 * (so each person reads their own language); action links are issued only if an email goes out.
 */
final readonly class NotificationMessage
{
    /**
     * @param  Closure(string $channel, string $locale): array{subject: string, body: string}  $content
     * @param  string  $sourceKey  identifies the cause (task + reason, step…) for idempotency
     * @param  list<string>  $channels
     * @param  (Closure(): array<string, string>)|null  $actions  decision => URL
     */
    public function __construct(
        public string $event,
        public NotificationCategory $category,
        public Closure $content,
        public ?string $url,
        public string $sourceKey,
        public array $channels = ['in_app', 'email'],
        public ?Closure $actions = null,
    ) {}
}
