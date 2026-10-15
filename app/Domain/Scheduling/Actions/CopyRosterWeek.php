<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Copies a week of a branch roster onto another week as drafts. Days where the person (or the
 * open shift slot) already has a shift are left alone. Returns how many shifts were copied.
 */
final readonly class CopyRosterWeek
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, Branch $branch, string $fromWeekStart, string $toWeekStart): int
    {
        $offset = (int) CarbonImmutable::parse($fromWeekStart)->diffInDays(CarbonImmutable::parse($toWeekStart), false);
        $source = RosterShift::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('date', [$fromWeekStart, CarbonImmutable::parse($fromWeekStart)->addDays(6)->toDateString()])
            ->get();
        $taken = RosterShift::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('date', [$toWeekStart, CarbonImmutable::parse($toWeekStart)->addDays(6)->toDateString()])
            ->whereNotNull('employee_id')
            ->get(['employee_id', 'date'])
            ->map(fn (RosterShift $s): string => $s->employee_id.'|'.$s->date->toDateString())
            ->flip();

        return $this->db->transaction(function () use ($actor, $source, $taken, $offset): int {
            $copied = 0;

            foreach ($source as $shift) {
                $date = $shift->date->addDays($offset)->toDateString();

                if ($shift->employee_id !== null && $taken->has($shift->employee_id.'|'.$date)) {
                    continue;
                }

                $copy = $shift->replicate(['ulid', 'published_at']);
                $copy->forceFill(['date' => $date, 'status' => ShiftStatus::Draft, 'created_by' => $actor->user?->id])->save();
                $copied++;
            }

            return $copied;
        });
    }
}
