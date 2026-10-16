<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Data\EventData;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\HolidayOccurrence;
use App\Domain\Scheduling\Data\MyShiftData;
use App\Domain\Scheduling\Support\MySchedule;
use Carbon\CarbonImmutable;

/**
 * Everything on one person's calendar in a range: meetings and bookings, published shifts, time
 * away and public holidays.
 */
final readonly class MyCalendar
{
    public function __construct(private BranchHolidays $holidays) {}

    /**
     * @return array{events: list<EventData>, shifts: list<MyShiftData>, timeAway: list<array{from: string, to: string, kind: string, status: string}>, holidays: list<array{date: string, name: array<string, string>}>}
     */
    public function for(User $user, string $fromDate, string $toDate, string $timezone): array
    {
        $from = CarbonImmutable::parse($fromDate, $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($toDate, $timezone)->addDay()->startOfDay()->utc();
        $occurrences = Occurrences::forUsers([$user->id], $from, $to)[$user->id] ?? [];

        foreach ($occurrences as $occurrence) {
            $occurrence->event->loadMissing('participants.user');
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        $branch = $employee?->current_branch_id === null ? null : Branch::withTrashed()->find($employee->current_branch_id);

        return [
            'events' => array_map(fn (Occurrence $o): EventData => EventData::fromOccurrence($o, $user->id), $occurrences),
            'shifts' => $employee instanceof Employee ? MySchedule::shifts($employee, $fromDate, $toDate) : [],
            'timeAway' => $employee instanceof Employee ? array_values(LeaveRequest::query()->where('employee_id', $employee->id)
                ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
                ->where('starts_on', '<=', $toDate)->where('ends_on', '>=', $fromDate)->get()
                ->map(fn (LeaveRequest $l): array => ['from' => $l->starts_on->toDateString(), 'to' => $l->ends_on->toDateString(), 'kind' => $l->kind->value, 'status' => $l->status->value])->all()) : [],
            'holidays' => $branch instanceof Branch ? array_map(fn (HolidayOccurrence $h): array => ['date' => $h->observedOn, 'name' => $h->name], $this->holidays->between($branch, $fromDate, $toDate)) : [],
        ];
    }
}
