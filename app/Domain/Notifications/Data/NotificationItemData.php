<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * In-app notification (handoff screen 44). Payload text is rendered server-side per locale
 * by the notification itself (ADR-0016).
 */
#[TypeScript]
final class NotificationItemData extends Data
{
    public function __construct(
        public string $id,
        public string $category,
        public string $title,
        public ?string $body,
        public ?string $url,
        public bool $read,
        public string $createdAt,
    ) {}

    public static function fromNotification(DatabaseNotification $notification): self
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return new self(
            id: $notification->id,
            category: is_string($data['category'] ?? null) ? $data['category'] : 'general',
            title: is_string($data['title'] ?? null) ? $data['title'] : '',
            body: is_string($data['body'] ?? null) ? $data['body'] : null,
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            read: $notification->read_at !== null,
            createdAt: $notification->created_at?->toIso8601String() ?? '',
        );
    }
}
