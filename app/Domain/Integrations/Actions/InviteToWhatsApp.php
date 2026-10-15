<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\Notifications\Support\TemplateRenderer;
use App\Support\Actors\Actor;

/**
 * WhatsApp activation invites (roadmap Phase 4, D58): everyone active without a verified number
 * is told, in the app and by email, how to connect theirs. Nothing is sent to WhatsApp itself.
 */
final readonly class InviteToWhatsApp
{
    public function __construct(private NotificationRouter $router, private TemplateRenderer $templates) {}

    public function handle(Actor $actor): int
    {
        $count = 0;
        $url = route('notifications.preferences');
        $batch = now()->format('YmdHi');

        User::query()->where('status', 'active')->whereNotIn('id', WhatsAppContact::query()->whereNotNull('verified_at')->select('user_id'))
            ->chunkById(200, function ($users) use (&$count, $url, $batch): void {
                foreach ($users as $user) {
                    $this->router->send($user, new NotificationMessage(
                        event: 'whatsapp.invite',
                        category: NotificationCategory::Security,
                        content: fn (string $channel, string $locale): array => $this->templates->render('whatsapp.invite', $channel, $locale, ['name' => $user->name, 'link' => $url]),
                        url: $url,
                        sourceKey: 'invite:'.$batch,
                        channels: ['in_app', 'email'],
                    ));
                    $count++;
                }
            });

        activity('integrations')->causedBy($actor->user)->event('whatsapp.invited')->withProperties(['count' => $count])->log('WhatsApp invites sent');

        return $count;
    }
}
