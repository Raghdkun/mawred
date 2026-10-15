<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\RosterNotifier;
use App\Support\Actors\Actor;

/** Removes a shift. If people could already see it, its person is told it was removed. */
final readonly class DeleteRosterShift
{
    public function __construct(private RosterNotifier $notifier) {}

    public function handle(Actor $actor, RosterShift $shift): void
    {
        $employee = $shift->employee_id === null ? null : Employee::withTrashed()->find($shift->employee_id);
        $wasPublished = $shift->status === ShiftStatus::Published;
        $date = $shift->date->toDateString();
        $ulid = $shift->ulid;

        activity('scheduling')->causedBy($actor->user)->performedOn($shift)->event('shift.deleted')
            ->withProperties(['date' => $date, 'published' => $wasPublished])->log('Shift removed');
        $shift->delete();

        if ($wasPublished && $employee instanceof Employee) {
            $this->notifier->tell($employee, 'schedule.shift_removed', 'removed:'.$ulid, fn (string $locale): array => [
                'subject' => __('A shift was removed', [], $locale),
                'body' => __('Your shift on :date was removed from the roster.', ['date' => $date], $locale),
            ]);
        }
    }
}
