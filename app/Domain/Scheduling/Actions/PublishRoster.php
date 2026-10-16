<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\RosterNotifier;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Publishes a branch's draft shifts in a date range: people see them in My schedule and get one
 * message each. Returns how many shifts were published.
 */
final readonly class PublishRoster
{
    public function __construct(private ConnectionInterface $db, private RosterNotifier $notifier) {}

    public function handle(Actor $actor, Branch $branch, string $from, string $to): int
    {
        $published = $this->db->transaction(function () use ($branch, $from, $to): array {
            $shifts = RosterShift::query()
                ->where('branch_id', $branch->id)
                ->where('status', ShiftStatus::Draft->value)
                ->whereBetween('date', [$from, $to])
                ->lockForUpdate()
                ->get(['id', 'employee_id']);

            RosterShift::query()->whereKey($shifts->pluck('id'))->update(['status' => ShiftStatus::Published->value, 'published_at' => now()]);

            return $shifts->all();
        });

        activity('scheduling')->causedBy($actor->user)->performedOn($branch)->event('roster.published')
            ->withProperties(['from' => $from, 'to' => $to, 'shifts' => count($published)])->log('Roster published');

        $counts = [];

        foreach ($published as $shift) {
            if ($shift->employee_id !== null) {
                $counts[$shift->employee_id] = ($counts[$shift->employee_id] ?? 0) + 1;
            }
        }

        foreach (Employee::query()->whereKey(array_keys($counts))->get() as $employee) {
            $count = $counts[$employee->id];
            $this->notifier->tell($employee, 'schedule.published', "published:{$branch->id}:{$from}:{$to}:".now()->getTimestamp(), fn (string $locale): array => [
                'subject' => __('Your schedule is ready', [], $locale),
                'body' => trans_choice('{1} One shift from :from to :to is on your schedule.|[2,*] :count shifts from :from to :to are on your schedule.', $count, ['count' => $count, 'from' => $from, 'to' => $to], $locale),
            ]);
        }

        return count($published);
    }
}
