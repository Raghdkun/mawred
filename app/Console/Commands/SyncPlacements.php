<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\People\Actions\SyncEmployeePlacement;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('people:sync-placements {--all : Re-sync every employee, not only those with a change due}')]
#[Description('Apply future-dated placements, hires and terminations on their branch-local day')]
final class SyncPlacements extends Command
{
    public function handle(SyncEmployeePlacement $sync): int
    {
        // Branch timezones span roughly UTC-12..+14, so look one day either side of UTC today.
        $from = CarbonImmutable::now('UTC')->subDays(2)->toDateString();
        $to = CarbonImmutable::now('UTC')->addDay()->toDateString();
        $count = 0;

        Employee::query()
            ->unless($this->option('all'), fn (Builder $query) => $query->where(function (Builder $due) use ($from, $to): void {
                $due->where('status', EmployeeStatus::Pending)
                    ->orWhereBetween('termination_date', [$from, $to])
                    ->orWhereHas('assignments', fn (Builder $a) => $a->whereBetween('effective_from', [$from, $to])->orWhereBetween('effective_to', [$from, $to]));
            }))
            ->orderBy('id')
            ->chunkById(500, function ($employees) use ($sync, &$count): void {
                foreach ($employees as $employee) {
                    $sync->handle($employee);
                    $count++;
                }
            });

        $this->components->info("{$count} employee(s) synced.");

        return self::SUCCESS;
    }
}
