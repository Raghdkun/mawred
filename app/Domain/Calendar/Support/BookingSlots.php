<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Models\BookingPage;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * The open slots on a booking page (D77): its weekly windows in its own timezone, cut into slots
 * of its length plus buffer, minus the owner's meetings and anything already booked or past.
 */
final class BookingSlots
{
    /**
     * @return list<array{0: int, 1: int}>
     */
    public static function between(BookingPage $page, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        $from = $from->max(CarbonImmutable::parse($page->starts_on->toDateString(), $page->timezone));

        if ($page->ends_on !== null) {
            $to = $to->min(CarbonImmutable::parse($page->ends_on->toDateString(), $page->timezone)->addDay());
        }

        if (! $page->is_active || $from->greaterThanOrEqualTo($to)) {
            return [];
        }

        $windows = [];

        for ($day = $from->setTimezone($page->timezone)->startOfDay(); $day->lessThan($to); $day = $day->addDay()) {
            foreach ($page->windows as $window) {
                if ($window['weekday'] === $day->dayOfWeekIso) {
                    $windows[] = [CarbonImmutable::parse($day->toDateString().' '.$window['from'], $page->timezone)->getTimestamp(), CarbonImmutable::parse($day->toDateString().' '.$window['to'], $page->timezone)->getTimestamp()];
                }
            }
        }

        $busy = [];

        foreach (Occurrences::forUsers([$page->owner_user_id], $from, $to)[$page->owner_user_id] ?? [] as $occurrence) {
            $response = $occurrence->event->participants->firstWhere('user_id', $page->owner_user_id)?->response;

            if (! $response instanceof Response || $response->isBusy()) {
                $busy[] = [$occurrence->start->getTimestamp() - $page->buffer_minutes * 60, $occurrence->end->getTimestamp() + $page->buffer_minutes * 60];
            }
        }

        $free = Intervals::subtract(Intervals::intersect($windows, [[$from->getTimestamp(), $to->getTimestamp()]]), $busy);
        $length = $page->duration_minutes * 60;
        $step = ($page->duration_minutes + $page->buffer_minutes) * 60;
        $slots = [];

        foreach ($free as [$start, $end]) {
            for ($cursor = $start; $cursor + $length <= $end; $cursor += $step) {
                if ($cursor > $now->getTimestamp()) {
                    $slots[] = [$cursor, $cursor + $length];
                }
            }
        }

        return $slots;
    }

    /** Whether someone may book from this page (its audience, D77); the owner can't book themselves. */
    public static function canBook(BookingPage $page, User $user): bool
    {
        if (! $page->is_active || $page->owner_user_id === $user->id) {
            return false;
        }

        if ($page->audience === 'everyone') {
            return true;
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();

        if (! $employee instanceof Employee) {
            return false;
        }

        if ($page->audience === 'branch') {
            return $page->branch_id !== null && $employee->current_branch_id === $page->branch_id;
        }

        $root = $page->department_id === null ? null : Department::withTrashed()->find($page->department_id);

        return $root instanceof Department && $employee->current_department_id !== null
            && Department::withTrashed()->withinSubtree($root)->whereKey($employee->current_department_id)->exists();
    }
}
