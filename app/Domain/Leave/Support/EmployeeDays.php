<?php

declare(strict_types=1);

namespace App\Domain\Leave\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\WeekendValue;
use App\Domain\Policies\Data\Values\WorkHoursValue;
use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\Calculators\WorkingDays;
use App\Domain\Policies\Support\PolicyResolver;
use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;

/**
 * An employee's calendar for leave maths: today on their branch, their working days (their
 * weekend, their branch's holidays) and the length of their working day.
 */
final class EmployeeDays
{
    /** @var array<int, Branch|null> */
    private array $branches = [];

    public function __construct(
        private readonly BranchHolidays $holidays,
        private readonly PolicyResolver $resolver,
        private readonly LocalClock $clock,
    ) {}

    public function branch(Employee $employee): ?Branch
    {
        if ($employee->current_branch_id === null) {
            return null;
        }

        return $this->branches[$employee->current_branch_id] ??= Branch::withTrashed()->find($employee->current_branch_id);
    }

    public function today(Employee $employee): string
    {
        $branch = $this->branch($employee);

        return $this->clock->today($branch instanceof Branch ? $branch->timezone : FieldSpec::string(config('app.timezone'), 'UTC'));
    }

    /**
     * Working dates in the inclusive range.
     *
     * @return list<string>
     */
    public function workingDates(Employee $employee, string $from, string $to): array
    {
        $weekend = $this->resolver->value($employee, $from, 'work.weekend');
        $days = $weekend instanceof WeekendValue ? array_map(intval(...), $weekend->days) : [6, 7];
        $branch = $this->branch($employee);

        return WorkingDays::between($from, $to, $days, $branch instanceof Branch ? $this->holidays->dates($branch, $from, $to) : []);
    }

    /** Paid hours in a standard day, for hour-based leave types. */
    public function hoursPerDay(Employee $employee, string $date): float
    {
        $hours = $this->resolver->value($employee, $date, 'work.hours');

        if (! $hours instanceof WorkHoursValue) {
            return 8.0;
        }

        $start = CarbonImmutable::parse($date.' '.$hours->startsAt);
        $end = CarbonImmutable::parse($date.' '.$hours->endsAt);
        $minutes = (int) $start->diffInMinutes($end->lessThanOrEqualTo($start) ? $end->addDay() : $end, true) - $hours->breakMinutes;

        return max(1, $minutes) / 60;
    }
}
