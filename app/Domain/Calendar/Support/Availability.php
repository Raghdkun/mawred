<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Enums\Visibility;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Support\ExpectedSchedule;
use Carbon\CarbonImmutable;

/**
 * Who is free when (handoff 27, D76): each person's working time (their shift, or their policy
 * hours; nothing on leave, holidays or the weekend) minus meetings they haven't declined. Busy
 * blocks carry a title only when the viewer is on the event, or the viewer's `calendar.view`
 * scope reaches the person and the event isn't private.
 */
final readonly class Availability
{
    public function __construct(private ExpectedSchedule $schedule, private ScopeResolver $resolver) {}

    /**
     * @param  list<User>  $people
     * @return list<array{userId: string, employeeId: string|null, working: list<array{0: int, 1: int}>, busy: list<array{start: int, end: int, kind: string, title: string|null}>, free: list<array{0: int, 1: int}>}>
     */
    public function for(User $viewer, array $people, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $occurrences = Occurrences::forUsers(array_map(fn (User $u): int => $u->id, $people), $from, $to);
        $employees = Employee::query()->whereIn('user_id', array_map(fn (User $u): int => $u->id, $people))->get()->keyBy('user_id');
        $out = [];

        foreach ($people as $person) {
            $employee = $employees->get($person->id);
            $titles = $person->id === $viewer->id || ($employee instanceof Employee && $this->resolver->allows($viewer, 'calendar.view', $employee, SubjectBinding::employee('employees.id')));
            $working = $employee instanceof Employee ? $this->working($employee, $from, $to) : [[$from->getTimestamp(), $to->getTimestamp()]];
            $busy = [];

            foreach ($occurrences[$person->id] ?? [] as $occurrence) {
                $response = $occurrence->event->participants->firstWhere('user_id', $person->id)?->response;

                if ($response instanceof Response && ! $response->isBusy()) {
                    continue;
                }

                $onIt = $occurrence->event->participants->contains('user_id', $viewer->id);
                $show = $onIt || ($titles && $occurrence->event->visibility !== Visibility::Private);
                $busy[] = ['start' => $occurrence->start->getTimestamp(), 'end' => $occurrence->end->getTimestamp(), 'kind' => 'event', 'title' => $show ? $occurrence->event->title : null];
            }

            if ($employee instanceof Employee) {
                foreach ($this->leave($employee, $from, $to) as $interval) {
                    $busy[] = ['start' => $interval[0], 'end' => $interval[1], 'kind' => 'leave', 'title' => null];
                }
            }

            $out[] = [
                'userId' => $person->ulid,
                'employeeId' => $employee?->ulid,
                'working' => $working,
                'busy' => $busy,
                'free' => Intervals::subtract($working, array_map(fn (array $b): array => [$b['start'], $b['end']], $busy)),
            ];
        }

        return $out;
    }

    /**
     * Working time per day in the range, as instants.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function working(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $intervals = [];
        $timezone = $this->schedule->for($employee, $from->toDateString())->timezone;

        for ($day = $from->setTimezone($timezone)->startOfDay(); $day->lessThan($to); $day = $day->addDay()) {
            $expected = $this->schedule->for($employee, $day->toDateString());

            if ($expected->start !== null && $expected->end !== null) {
                $intervals[] = [$day->addMinutes($expected->start)->getTimestamp(), $day->addMinutes($expected->end)->getTimestamp()];
            }
        }

        return Intervals::intersect($intervals, [[$from->getTimestamp(), $to->getTimestamp()]]);
    }

    /**
     * Pending or approved hourly permissions as busy time (full days already drop out of working time).
     *
     * @return list<array{0: int, 1: int}>
     */
    private function leave(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = $this->schedule->for($employee, $from->toDateString())->timezone;
        $out = [];

        foreach (LeaveRequest::query()->where('employee_id', $employee->id)->where('kind', LeaveRequestKind::Hourly->value)
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()])->get() as $leave) {
            if ($leave->from_time !== null && $leave->to_time !== null) {
                $date = $leave->starts_on->toDateString();
                $out[] = [CarbonImmutable::parse($date.' '.$leave->from_time, $timezone)->getTimestamp(), CarbonImmutable::parse($date.' '.$leave->to_time, $timezone)->getTimestamp()];
            }
        }

        return $out;
    }
}
