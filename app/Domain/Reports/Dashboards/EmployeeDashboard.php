<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

use App\Domain\Access\Models\User;
use App\Domain\Attendance\Enums\PunchKind;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Calendar\Data\EventData;
use App\Domain\Calendar\Support\MyCalendar;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Data\EmployeeDashboardData;
use App\Domain\Scheduling\Data\MyShiftData;
use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;

/**
 * The employee home's check-in card and the next seven days (handoff 4, D123): shifts,
 * meetings, interviews, training sessions, time away and holidays, on the person's branch date.
 */
final readonly class EmployeeDashboard
{
    public const int AGENDA_DAYS = 7;

    public const int AGENDA_ITEMS = 8;

    public function __construct(private EmployeeDays $days, private MyCalendar $calendar, private LocalClock $clock) {}

    public function build(User $user, string $locale): EmployeeDashboardData
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();
        $branch = $employee instanceof Employee ? $this->days->branch($employee) : null;
        $zone = $branch instanceof Branch ? $branch->timezone : FieldSpec::string(config('app.timezone'), 'UTC');
        $today = $employee instanceof Employee ? $this->days->today($employee) : $this->clock->today($zone);
        $until = CarbonImmutable::parse($today)->addDays(self::AGENDA_DAYS - 1)->toDateString();
        $mine = $this->calendar->for($user, $today, $until, $zone);

        return new EmployeeDashboardData(
            clock: $employee instanceof Employee ? $this->clock($employee, $today, $zone, $mine['shifts']) : null,
            agenda: $this->agenda($mine, $today, $locale),
        );
    }

    /**
     * @param  list<MyShiftData>  $shifts
     * @return array{date: string, timezone: string, status: string|null, firstIn: string|null, lastOut: string|null, shift: array{startsAt: string, endsAt: string}|null, next: 'in'|'out'}
     */
    private function clock(Employee $employee, string $today, string $zone, array $shifts): array
    {
        $day = AttendanceDay::query()->where('employee_id', $employee->id)->where('date', $today)->first();
        $last = AttendanceEvent::query()->where('employee_id', $employee->id)->whereNull('voided_at')
            ->where('occurred_at', '>=', CarbonImmutable::now()->subDays(2))->latest('occurred_at')->first();
        $shift = null;

        foreach ($shifts as $candidate) {
            if ($candidate->date === $today) {
                $shift = ['startsAt' => $candidate->startsAt, 'endsAt' => $candidate->endsAt];

                break;
            }
        }

        return [
            'date' => $today,
            'timezone' => $zone,
            'status' => $day?->status->value,
            'firstIn' => $day?->first_in?->toIso8601String(),
            'lastOut' => $day?->last_out?->toIso8601String(),
            'shift' => $shift,
            'next' => $last instanceof AttendanceEvent
                ? ($last->kind === PunchKind::In ? 'out' : 'in')
                : ($day?->first_in !== null && $day->last_out === null ? 'out' : 'in'),
        ];
    }

    /**
     * @param  array{events: list<EventData>, shifts: list<MyShiftData>, timeAway: list<array{from: string, to: string, kind: string, status: string}>, holidays: list<array{date: string, name: array<string, string>}>}  $mine
     * @return list<array{key: string, kind: 'shift'|'meeting'|'booking'|'interview'|'training'|'leave'|'holiday', title: string|null, date: string, startsAt: string|null, endsAt: string|null, timezone: string|null}>
     */
    private function agenda(array $mine, string $today, string $locale): array
    {
        $items = [];

        foreach ($mine['shifts'] as $shift) {
            $name = $shift->name === null ? null : ($shift->name[$locale] ?? $shift->name['en'] ?? null);
            $items[] = ['key' => 'shift-'.$shift->id, 'kind' => 'shift', 'title' => is_string($name) ? $name : null, 'date' => $shift->date, 'startsAt' => $shift->startsAt, 'endsAt' => $shift->endsAt, 'timezone' => null];
        }

        foreach ($mine['events'] as $event) {
            $kind = in_array($event->kind, ['meeting', 'booking', 'interview', 'training'], true) ? $event->kind : 'meeting';
            $items[] = ['key' => 'event-'.$event->key, 'kind' => $kind, 'title' => $event->title, 'date' => CarbonImmutable::parse($event->startsAt)->setTimezone($event->timezone)->toDateString(), 'startsAt' => $event->startsAt, 'endsAt' => $event->endsAt, 'timezone' => $event->timezone];
        }

        foreach ($mine['timeAway'] as $index => $away) {
            if ($away['status'] === 'approved') {
                $items[] = ['key' => 'leave-'.$index, 'kind' => 'leave', 'title' => null, 'date' => max($away['from'], $today), 'startsAt' => null, 'endsAt' => $away['to'], 'timezone' => null];
            }
        }

        foreach ($mine['holidays'] as $holiday) {
            $items[] = ['key' => 'holiday-'.$holiday['date'], 'kind' => 'holiday', 'title' => $holiday['name'][$locale] ?? $holiday['name']['en'] ?? null, 'date' => $holiday['date'], 'startsAt' => null, 'endsAt' => null, 'timezone' => null];
        }

        usort($items, fn (array $a, array $b): int => [$a['date'], $a['startsAt'] ?? ''] <=> [$b['date'], $b['startsAt'] ?? '']);

        return array_slice($items, 0, self::AGENDA_ITEMS);
    }
}
