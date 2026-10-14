<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Support\BranchHolidays;

/**
 * Archives a calendar. Branches stop observing it; its rules stay with the archived record.
 */
final readonly class DeleteHolidayCalendar
{
    public function __construct(private BranchHolidays $holidays) {}

    public function handle(HolidayCalendar $calendar): void
    {
        $calendar->branches()->detach();
        $calendar->delete();
        $this->holidays->forget();
    }
}
