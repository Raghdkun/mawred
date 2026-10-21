<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Mail\NotificationMail;
use App\Domain\Notifications\Support\DeliveryLog;
use App\Domain\Notifications\Support\EmailSuppressions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;

/**
 * The daily digest (ADR-0016): one email per person who chose it, listing the unread,
 * non-critical notifications from the last day, at most once a day per person.
 */
#[Signature('notifications:digest')]
#[Description('Email the daily notification digest to people who chose it')]
final class NotificationsDigest extends Command
{
    public function handle(DeliveryLog $log): int
    {
        $categories = array_values(array_map(fn (NotificationCategory $c): string => $c->value, array_filter(NotificationCategory::cases(), fn (NotificationCategory $c): bool => ! $c->isCritical())));
        $sent = 0;

        User::query()->where('digest_enabled', true)->where('status', 'active')->chunkById(200, function ($users) use ($log, $categories, &$sent): void {
            foreach ($users as $user) {
                $key = 'digest:'.$user->id.':'.now()->toDateString();

                if ($log->seen($key)) {
                    continue;
                }

                $items = array_values($user->unreadNotifications()->where('created_at', '>=', now()->subDay())->limit(50)->get()
                    ->filter(fn (DatabaseNotification $n): bool => in_array($n->data['category'] ?? null, $categories, true))
                    ->map(fn (DatabaseNotification $n): array => [
                        'title' => is_string($n->data['title'] ?? null) ? $n->data['title'] : '',
                        'body' => is_string($n->data['body'] ?? null) ? $n->data['body'] : null,
                        'url' => is_string($n->data['url'] ?? null) ? $n->data['url'] : null,
                    ])->all());

                if ($items === [] || EmailSuppressions::blocks($user->email)) {
                    continue;
                }

                $locale = $user->locale === 'ar' ? 'ar' : 'en';
                Mail::to($user)->queue(new NotificationMail(
                    subjectLine: __('Your Mawred digest', [], $locale),
                    bodyText: trans_choice('{1} You have :count update since yesterday.|[2,*] You have :count updates since yesterday.', count($items), ['count' => count($items)], $locale),
                    url: route('dashboard'),
                    recipientLocale: $locale,
                    items: $items,
                ));
                $log->record($user->id, 'digest', 'digest', 'email', 'queued', $key);
                $sent++;
            }
        });

        $this->components->info("Digests queued: {$sent}");

        return self::SUCCESS;
    }
}
