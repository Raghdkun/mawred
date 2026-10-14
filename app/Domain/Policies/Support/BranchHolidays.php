<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\HolidayRule;
use App\Domain\Policies\Support\Calculators\HolidayCalculator;
use App\Domain\Policies\Support\Calculators\WorkingDays;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Holidays a branch observes, from every active calendar assigned to it (D47). Shifts use the
 * branch's own weekend. Rules are loaded once per branch per request (scoped binding).
 */
final class BranchHolidays
{
    /** @var array<int, list<HolidayRuleSpec>> */
    private array $rules = [];

    /**
     * @return list<HolidayOccurrence>
     */
    public function between(Branch $branch, string $from, string $to): array
    {
        return HolidayCalculator::between($this->rulesFor($branch), $from, $to, $this->weekend($branch));
    }

    /**
     * Distinct observed dates; two calendars marking the same day count it once.
     *
     * @return list<string>
     */
    public function dates(Branch $branch, string $from, string $to): array
    {
        return array_values(array_unique(array_map(fn (HolidayOccurrence $o): string => $o->observedOn, $this->between($branch, $from, $to))));
    }

    public function isHoliday(Branch $branch, string $date): bool
    {
        return $this->dates($branch, $date, $date) !== [];
    }

    /**
     * @param  list<int>|null  $weekend  the employee's resolved weekend; the branch's when null
     */
    public function workingDays(Branch $branch, string $from, string $to, ?array $weekend = null): int
    {
        return WorkingDays::count($from, $to, $weekend ?? $this->weekend($branch), $this->dates($branch, $from, $to));
    }

    public function forget(): void
    {
        $this->rules = [];
    }

    /**
     * @return list<HolidayRuleSpec>
     */
    private function rulesFor(Branch $branch): array
    {
        return $this->rules[$branch->id] ??= array_values(HolidayRule::query()
            ->whereIn('holiday_calendar_id', HolidayCalendar::query()
                ->where('is_active', true)
                ->whereHas('branches', fn (Builder $q) => $q->whereKey($branch->id))
                ->select('id'))
            ->get()
            ->map(fn (HolidayRule $rule): HolidayRuleSpec => $rule->toSpec())
            ->all());
    }

    /**
     * @return list<int>
     */
    private function weekend(Branch $branch): array
    {
        return array_map(intval(...), $branch->weekend_days);
    }
}
