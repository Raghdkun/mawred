<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\RosterNotifier;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * An approved shift swap (D72): the two people's published shifts on the date change hands; if
 * the colleague had none, they take the requester's shift. Running it again changes nothing.
 */
final readonly class SwapShifts
{
    public function __construct(private ConnectionInterface $db, private RosterNotifier $notifier) {}

    public function handle(Actor $actor, Employee $requester, Employee $colleague, string $date, string $key): void
    {
        $moved = $this->db->transaction(function () use ($requester, $colleague, $date, $key): bool {
            $mine = RosterShift::query()->where('employee_id', $requester->id)->whereDate('date', $date)->where('status', ShiftStatus::Published->value)->lockForUpdate()->get();
            $theirs = RosterShift::query()->where('employee_id', $colleague->id)->whereDate('date', $date)->where('status', ShiftStatus::Published->value)->lockForUpdate()->get();

            if ($mine->isEmpty()) {
                if ($theirs->contains(fn (RosterShift $s): bool => $s->note === 'swap:'.$key)) {
                    return false;
                }

                throw new DomainRuleViolation('scheduling.no_shift', __('There is no published shift to swap on that date.'));
            }

            RosterShift::query()->whereKey($mine->pluck('id'))->update(['employee_id' => $colleague->id, 'note' => 'swap:'.$key]);
            RosterShift::query()->whereKey($theirs->pluck('id'))->update(['employee_id' => $requester->id, 'note' => 'swap:'.$key]);

            return true;
        });

        if (! $moved) {
            return;
        }

        activity('scheduling')->causedBy($actor->user)->performedOn($requester)->event('shift.swapped')
            ->withProperties(['date' => $date, 'with' => $colleague->ulid])->log('Shifts swapped');

        foreach ([$requester, $colleague] as $employee) {
            $this->notifier->tell($employee, 'schedule.swapped', 'swap:'.$key.':'.$employee->id, fn (string $locale): array => [
                'subject' => __('Shift swap done', [], $locale),
                'body' => __('Your schedule for :date has been updated after the swap.', ['date' => $date], $locale),
            ]);
        }
    }
}
