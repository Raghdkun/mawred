<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use Carbon\CarbonImmutable;

/**
 * Attendance-day arithmetic in the branch timezone (ADR-0004): which day a punch counts for (a
 * punch after midnight belongs to last night's overnight shift until 4 hours after it ends) and
 * minutes from a day's local midnight.
 */
final readonly class AttendanceClock
{
    private const int OVERNIGHT_SLACK_MINUTES = 240;

    public function __construct(private EmployeeDays $days) {}

    public function timezone(Employee $employee): string
    {
        $branch = $this->days->branch($employee);

        return $branch instanceof Branch ? $branch->timezone : FieldSpec::string(config('app.timezone'), 'UTC');
    }

    public function dayOf(Employee $employee, CarbonImmutable $instant): string
    {
        $local = $instant->setTimezone($this->timezone($employee));
        $yesterday = $local->subDay()->toDateString();
        $minute = $local->hour * 60 + $local->minute;

        $overnight = RosterShift::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $yesterday)
            ->where('status', ShiftStatus::Published->value)
            ->get()
            ->contains(function (RosterShift $s) use ($minute): bool {
                $end = $s->span()[1];

                return $end > 1440 && $minute + 1440 <= $end + self::OVERNIGHT_SLACK_MINUTES;
            });

        return $overnight ? $yesterday : $local->toDateString();
    }

    public function minutesInto(string $date, CarbonImmutable $instant, string $timezone): int
    {
        $midnight = CarbonImmutable::parse($date, $timezone)->startOfDay();

        return (int) floor(($instant->getTimestamp() - $midnight->getTimestamp()) / 60);
    }

    public function instantAt(string $date, int $minutes, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $timezone)->startOfDay()->addMinutes($minutes)->utc();
    }
}
