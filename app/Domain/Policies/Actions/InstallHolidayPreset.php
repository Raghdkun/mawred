<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Data\HolidayCalendarInputData;
use App\Domain\Policies\Data\HolidayRuleInputData;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Support\HolidayPresets;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates a calendar from a preset. Installing it again returns the existing calendar.
 */
final readonly class InstallHolidayPreset
{
    public function __construct(
        private ConnectionInterface $db,
        private SaveHolidayCalendar $calendars,
        private SaveHolidayRule $rules,
    ) {}

    /**
     * @param  list<string>  $branchIds  branch ULIDs
     */
    public function handle(Actor $actor, string $key, array $branchIds = []): HolidayCalendar
    {
        $preset = HolidayPresets::all()[$key] ?? throw new DomainRuleViolation('holidays.unknown_preset', __('Unknown holiday preset.'));

        $existing = HolidayCalendar::query()->where('preset_key', $key)->first();

        if ($existing instanceof HolidayCalendar) {
            return $existing;
        }

        return $this->db->transaction(function () use ($actor, $key, $preset, $branchIds): HolidayCalendar {
            $calendar = $this->calendars->handle($actor, new HolidayCalendarInputData(
                code: $preset['code'],
                name: $preset['name'],
                countryCode: $preset['country'],
                branchIds: $branchIds,
            ));
            $calendar->forceFill(['preset_key' => $key])->save();

            foreach ($preset['rules'] as $rule) {
                $this->rules->handle($calendar, HolidayRuleInputData::from($rule));
            }

            return $calendar;
        });
    }
}
