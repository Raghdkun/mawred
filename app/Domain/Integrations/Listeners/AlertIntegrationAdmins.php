<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Listeners;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Events\IntegrationHealthChanged;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\Notifications\Support\TemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Tells everyone who manages integrations that a connection changed health (ADR-0015). */
final readonly class AlertIntegrationAdmins implements ShouldQueue
{
    public function __construct(private NotificationRouter $router, private TemplateRenderer $templates) {}

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function handle(IntegrationHealthChanged $event): void
    {
        if ($event->to === IntegrationStatus::Unverified) {
            return;
        }

        $url = route('integrations.index');
        $type = IntegrationType::from($event->connection->type);

        foreach (User::permission('integrations.configure')->where('status', 'active')->get() as $user) {
            $this->router->send($user, new NotificationMessage(
                event: 'integrations.health_changed',
                category: NotificationCategory::Integrations,
                content: fn (string $channel, string $locale): array => $this->templates->render('integrations.health_changed', $channel, $locale, [
                    'name' => $user->name,
                    'integration' => $type->label($locale),
                    'status' => $event->to->label($locale),
                    'error' => $event->connection->last_error ?? '',
                    'link' => $url,
                ]),
                url: $url,
                sourceKey: $event->connection->ulid.':'.$event->to->value.':'.now()->format('YmdHi'),
                channels: ['in_app', 'email'],
            ));
        }
    }
}
