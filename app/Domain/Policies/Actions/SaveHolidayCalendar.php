<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Policies\Data\HolidayCalendarInputData;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Support\BranchHolidays;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates or edits a shared holiday calendar and the branches that observe it (D47). The code is
 * fixed once created.
 */
final readonly class SaveHolidayCalendar
{
    public function __construct(private ConnectionInterface $db, private BranchHolidays $holidays) {}

    public function handle(Actor $actor, HolidayCalendarInputData $input, ?HolidayCalendar $calendar = null): HolidayCalendar
    {
        $calendar ??= new HolidayCalendar;
        $code = $calendar->exists ? $calendar->code : ReferenceValues::code($input->code);

        if (! $calendar->exists) {
            ReferenceValues::assertCodeAvailable(HolidayCalendar::class, $code);
        }

        $this->db->transaction(function () use ($actor, $input, $calendar, $code): void {
            $calendar->setTranslations('name', ReferenceValues::translations($input->name));
            $calendar->fill([
                'code' => $code,
                'country_code' => $input->countryCode === null ? null : mb_strtoupper($input->countryCode),
                'is_active' => $input->isActive,
            ])->save();

            $branchIds = Branch::query()->whereIn('ulid', $input->branchIds)->pluck('id')->all();
            $changes = $calendar->branches()->sync($branchIds);

            if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                activity('policies')->causedBy($actor->user)->performedOn($calendar)
                    ->withProperties(['attached' => $changes['attached'], 'detached' => $changes['detached']])
                    ->event('branches.changed')->log('Holiday calendar branches changed');
            }
        });

        $this->holidays->forget();

        return $calendar;
    }
}
