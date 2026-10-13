<?php

declare(strict_types=1);

use App\Support\Database\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    DB::statement('CREATE TABLE partition_probe (id bigint GENERATED ALWAYS AS IDENTITY, at timestamptz NOT NULL, PRIMARY KEY (id, at)) PARTITION BY RANGE (at)');
});

it('creates the default and upcoming monthly partitions idempotently', function (): void {
    $manager = resolve(PartitionManager::class);

    $created = $manager->ensureMonthlyPartitions('partition_probe', CarbonImmutable::parse('2026-10-15'), 2);
    $again = $manager->ensureMonthlyPartitions('partition_probe', CarbonImmutable::parse('2026-10-15'), 2);

    expect($created)->toBe(['partition_probe_p202610', 'partition_probe_p202611', 'partition_probe_p202612'])
        ->and($again)->toBe([])
        ->and($manager->monthlyPartitions('partition_probe'))->toHaveCount(3);

    DB::table('partition_probe')->insert(['at' => '2030-01-01 00:00:00+00']); // lands in the default partition
    expect(DB::table('partition_probe_default')->count())->toBe(1);
});

it('drops only partitions before the cutoff', function (): void {
    $manager = resolve(PartitionManager::class);
    $manager->ensureMonthlyPartitions('partition_probe', CarbonImmutable::parse('2026-01-01'), 5);

    $dropped = $manager->dropPartitionsBefore('partition_probe', CarbonImmutable::parse('2026-04-01'));

    expect($dropped)->toBe(['partition_probe_p202601', 'partition_probe_p202602', 'partition_probe_p202603'])
        ->and($manager->monthlyPartitions('partition_probe'))->toBe(['partition_probe_p202604', 'partition_probe_p202605', 'partition_probe_p202606']);
});

it('rejects unsafe identifiers', function (): void {
    expect(fn () => resolve(PartitionManager::class)->ensureDefaultPartition('x; drop table users'))
        ->toThrow(InvalidArgumentException::class);
});
