<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ClaimStatus;
use App\Domain\Scheduling\Models\OpenShiftClaim;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\RosterNotifier;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/** The roster manager gives an open shift to one claimant (D73); the others are told it's gone. */
final readonly class AssignOpenShift
{
    public function __construct(private ConnectionInterface $db, private RosterNotifier $notifier) {}

    public function handle(?Actor $actor, OpenShiftClaim $claim): void
    {
        [$winner, $losers, $date] = $this->db->transaction(function () use ($claim): array {
            $shift = RosterShift::query()->lockForUpdate()->findOrFail($claim->roster_shift_id);

            if (! $shift->isOpen() || $claim->status !== ClaimStatus::Pending) {
                throw new DomainRuleViolation('scheduling.not_open', __('This shift is no longer open.'));
            }

            $shift->forceFill(['employee_id' => $claim->employee_id, 'auto_assign' => false])->save();
            $claim->forceFill(['status' => ClaimStatus::Accepted])->save();
            $losers = OpenShiftClaim::query()->where('roster_shift_id', $shift->id)->whereKeyNot($claim->id)->where('status', ClaimStatus::Pending->value)->get();
            OpenShiftClaim::query()->whereKey($losers->pluck('id'))->update(['status' => ClaimStatus::Declined->value]);

            return [$claim->employee_id, $losers->pluck('employee_id')->all(), $shift->date->toDateString()];
        });

        activity('scheduling')->causedBy($actor?->user)->performedOn($claim)->event('open_shift.assigned')->log('Open shift assigned');

        foreach (Employee::query()->whereKey([$winner, ...$losers])->get() as $employee) {
            $won = $employee->id === $winner;
            $this->notifier->tell($employee, $won ? 'schedule.open_shift_won' : 'schedule.open_shift_lost', 'claim:'.$claim->ulid.':'.$employee->id, fn (string $locale): array => $won
                ? ['subject' => __('The open shift is yours', [], $locale), 'body' => __('You have the open shift on :date.', ['date' => $date], $locale)]
                : ['subject' => __('The open shift went to someone else', [], $locale), 'body' => __('The open shift on :date was given to someone else.', ['date' => $date], $locale)]);
        }
    }
}
