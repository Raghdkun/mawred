<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('partitions:maintain {--dry-run : Report what would change without dropping anything}')]
#[Description('Create upcoming monthly partitions and drop partitions past retention (ADR-0018)')]
final class MaintainPartitions extends Command
{
    public function handle(PartitionManager $partitions): int
    {
        $monthsAhead = config()->integer('partitioning.months_ahead', 3);
        /** @var array<string, array{column: string, retention_months: int|null}> $tables */
        $tables = config('partitioning.tables', []);
        $now = CarbonImmutable::now('UTC');

        foreach ($tables as $table => $options) {
            $created = $partitions->ensureMonthlyPartitions($table, $now, $monthsAhead);
            $this->components->info(sprintf('%s: %d partition(s) created', $table, count($created)));

            if ($options['retention_months'] === null) {
                continue;
            }

            $cutoff = $now->startOfMonth()->subMonths($options['retention_months']);

            if ($this->option('dry-run')) {
                $this->components->warn(sprintf('%s: would drop partitions before %s', $table, $cutoff->format('Y-m')));

                continue;
            }

            $dropped = $partitions->dropPartitionsBefore($table, $cutoff);
            $this->components->info(sprintf('%s: %d partition(s) past retention dropped', $table, count($dropped)));
        }

        return self::SUCCESS;
    }
}
