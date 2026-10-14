<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Data\NotificationPreferencesData;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Support\NotificationPreferences;
use Illuminate\Database\ConnectionInterface;

/**
 * Saves someone's notification settings. Channels that can't be switched off (critical in-app,
 * security email) are ignored rather than stored, so the matrix never lies.
 */
final readonly class SaveNotificationPreferences
{
    public function __construct(private ConnectionInterface $db, private NotificationPreferences $preferences) {}

    public function handle(User $user, NotificationPreferencesData $data): void
    {
        $rows = [];

        foreach (NotificationCategory::cases() as $category) {
            foreach ($category->channels() as $channel) {
                $enabled = $data->channels[$category->value][$channel] ?? null;

                if (is_bool($enabled) && $category->canDisable($channel)) {
                    $rows[] = ['user_id' => $user->id, 'category' => $category->value, 'channel' => $channel, 'enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }

        $this->db->transaction(function () use ($user, $data, $rows): void {
            if ($rows !== []) {
                NotificationPreference::query()->upsert($rows, ['user_id', 'category', 'channel'], ['enabled', 'updated_at']);
            }

            $user->forceFill([
                'quiet_hours_start' => $data->quietHoursStart,
                'quiet_hours_end' => $data->quietHoursEnd,
                'digest_enabled' => $data->digest,
            ])->save();
        });

        $this->preferences->forget($user);
    }
}
