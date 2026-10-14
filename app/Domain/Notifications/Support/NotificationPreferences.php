<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\People\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * A person's notification choices (ADR-0016): channel per category (on unless switched off,
 * and critical in-app copies can't be), quiet hours in their own timezone, and the daily digest.
 */
final class NotificationPreferences
{
    /** @var array<int, array<string, bool>> */
    private array $loaded = [];

    public function enabled(User $user, NotificationCategory $category, string $channel): bool
    {
        if (! $category->canDisable($channel)) {
            return true;
        }

        $this->loaded[$user->id] ??= NotificationPreference::query()->where('user_id', $user->id)->get()
            ->mapWithKeys(fn (NotificationPreference $row): array => [$row->category.'.'.$row->channel => $row->enabled])
            ->all();

        return $this->loaded[$user->id][$category->value.'.'.$channel] ?? true;
    }

    /**
     * The preference matrix for the settings screen.
     *
     * @return list<array{category: string, critical: bool, channels: array<string, array{enabled: bool, locked: bool}>}>
     */
    public function matrix(User $user): array
    {
        return array_map(fn (NotificationCategory $category): array => [
            'category' => $category->value,
            'critical' => $category->isCritical(),
            'channels' => collect($category->channels())->mapWithKeys(fn (string $channel): array => [$channel => [
                'enabled' => $this->enabled($user, $category, $channel),
                'locked' => ! $category->canDisable($channel),
            ]])->all(),
        ], NotificationCategory::cases());
    }

    public function forget(User $user): void
    {
        unset($this->loaded[$user->id]);
    }

    public function timezone(User $user): string
    {
        if (is_string($user->timezone) && $user->timezone !== '') {
            return $user->timezone;
        }

        $branchTimezone = Employee::query()->where('user_id', $user->id)->first()?->currentBranch?->timezone;

        return is_string($branchTimezone) && $branchTimezone !== '' ? $branchTimezone : 'UTC';
    }

    /**
     * When quiet hours end, if the person is inside them right now; null otherwise. Spans that
     * cross midnight (22:00–07:00) are handled.
     */
    public function quietUntil(User $user, CarbonImmutable $now): ?CarbonImmutable
    {
        $start = $user->quiet_hours_start;
        $end = $user->quiet_hours_end;

        if (! is_string($start) || ! is_string($end) || $start === $end) {
            return null;
        }

        $local = $now->setTimezone($this->timezone($user));
        $time = $local->format('H:i:s');
        $start = strlen($start) === 5 ? $start.':00' : $start;
        $end = strlen($end) === 5 ? $end.':00' : $end;
        $overnight = $start > $end;
        $quiet = $overnight ? ($time >= $start || $time < $end) : ($time >= $start && $time < $end);

        if (! $quiet) {
            return null;
        }

        $until = $local->setTimeFromTimeString($end);

        return ($until <= $local ? $until->addDay() : $until)->utc();
    }
}
