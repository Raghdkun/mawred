<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

use App\Domain\Notifications\Enums\NotificationCategory;
use Spatie\LaravelData\Data;

/**
 * Notification settings from the preferences screen (handoff 44): channel switches per
 * category, quiet hours (both or neither) and the daily digest.
 */
final class NotificationPreferencesData extends Data
{
    /**
     * @param  array<string, array<string, bool>>  $channels  category => channel => enabled
     */
    public function __construct(
        public array $channels,
        public ?string $quietHoursStart,
        public ?string $quietHoursEnd,
        public bool $digest,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $categories = implode(',', array_map(fn (NotificationCategory $c): string => $c->value, NotificationCategory::cases()));

        return [
            'channels' => ['present', 'array:'.$categories],
            'channels.*' => ['array:in_app,email'],
            'channels.*.*' => ['boolean'],
            'quietHoursStart' => ['nullable', 'date_format:H:i', 'required_with:quietHoursEnd', 'different:quietHoursEnd'],
            'quietHoursEnd' => ['nullable', 'date_format:H:i', 'required_with:quietHoursStart'],
            'digest' => ['required', 'boolean'],
        ];
    }
}
