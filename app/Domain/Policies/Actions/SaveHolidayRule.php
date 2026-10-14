<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Policies\Data\HolidayRuleInputData;
use App\Domain\Policies\Enums\HolidayType;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\HolidayRule;
use App\Domain\Policies\Support\BranchHolidays;
use App\Support\Errors\DomainRuleViolation;

/**
 * Adds or edits a rule. Only the fields its type uses are kept, so switching type never leaves
 * stale values behind.
 */
final readonly class SaveHolidayRule
{
    public function __construct(private BranchHolidays $holidays) {}

    public function handle(HolidayCalendar $calendar, HolidayRuleInputData $input, ?HolidayRule $rule = null): HolidayRule
    {
        if ($input->type === HolidayType::Fixed && ! checkdate((int) $input->month, (int) $input->day, 2024)) {
            throw new DomainRuleViolation('holidays.invalid_day', __('That day does not exist in that month.'), ['day' => [__('That day does not exist in that month.')]]);
        }

        $rule ??= new HolidayRule(['holiday_calendar_id' => $calendar->id]);
        $rule->setTranslations('name', ReferenceValues::translations($input->name));
        $rule->fill([
            'type' => $input->type,
            'month' => $input->type === HolidayType::OneOff ? null : $input->month,
            'day' => $input->type === HolidayType::Fixed ? $input->day : null,
            'weekday' => $input->type === HolidayType::NthWeekday ? $input->weekday : null,
            'nth' => $input->type === HolidayType::NthWeekday ? $input->nth : null,
            'date' => $input->type === HolidayType::OneOff ? $input->date : null,
            'duration_days' => $input->durationDays,
            'observed' => $input->observed,
            'hijri_based' => $input->hijriBased,
            'from_year' => $input->type === HolidayType::OneOff ? null : $input->fromYear,
            'to_year' => $input->type === HolidayType::OneOff ? null : $input->toYear,
        ])->save();

        $this->holidays->forget();

        return $rule;
    }
}
