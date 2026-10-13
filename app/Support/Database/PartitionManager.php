<?php

declare(strict_types=1);

namespace App\Support\Database;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Creates and retires monthly range partitions on PostgreSQL (ADR-0018).
 *
 * Partition names follow `<table>_pYYYYMM`; every table also has a `<table>_default` partition so
 * an insert can never fail because a month was not created in time.
 */
final readonly class PartitionManager
{
    public function __construct(private ConnectionInterface $db) {}

    public function ensureDefaultPartition(string $table): void
    {
        $this->assertIdentifier($table);

        $this->db->statement(sprintf(
            'CREATE TABLE IF NOT EXISTS %1$s_default PARTITION OF %1$s DEFAULT',
            $table,
        ));
    }

    /**
     * Ensure partitions exist from the month of `$from` through `$monthsAhead` months later.
     *
     * @return list<string> partitions created by this call
     */
    public function ensureMonthlyPartitions(string $table, CarbonImmutable $from, int $monthsAhead): array
    {
        $this->assertIdentifier($table);
        $this->ensureDefaultPartition($table);

        $created = [];
        $month = $from->utc()->startOfMonth();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $name = $this->partitionName($table, $month);

            if (! $this->exists($name)) {
                $this->db->statement(sprintf(
                    "CREATE TABLE %s PARTITION OF %s FOR VALUES FROM ('%s') TO ('%s')",
                    $name,
                    $table,
                    $month->toDateString(),
                    $month->addMonth()->toDateString(),
                ));
                $created[] = $name;
            }

            $month = $month->addMonth();
        }

        return $created;
    }

    /**
     * Detach and drop monthly partitions that end on or before the start of `$cutoffMonth`.
     *
     * @return list<string> partitions dropped
     */
    public function dropPartitionsBefore(string $table, CarbonImmutable $cutoffMonth): array
    {
        $this->assertIdentifier($table);

        $cutoff = $this->partitionName($table, $cutoffMonth->utc()->startOfMonth());
        $dropped = [];

        foreach ($this->monthlyPartitions($table) as $partition) {
            if (strcmp($partition, $cutoff) < 0) {
                $this->db->statement(sprintf('ALTER TABLE %s DETACH PARTITION %s', $table, $partition));
                $this->db->statement(sprintf('DROP TABLE %s', $partition));
                $dropped[] = $partition;
            }
        }

        return $dropped;
    }

    /**
     * @return list<string>
     */
    public function monthlyPartitions(string $table): array
    {
        $this->assertIdentifier($table);

        /** @var list<object{name: string}> $rows */
        $rows = $this->db->select(
            'SELECT c.relname AS name FROM pg_inherits i
             JOIN pg_class c ON c.oid = i.inhrelid
             JOIN pg_class p ON p.oid = i.inhparent
             WHERE p.relname = ? AND c.relname ~ ?
             ORDER BY c.relname',
            [$table, '^'.$table.'_p[0-9]{6}$'],
        );

        return array_map(fn (object $row): string => $row->name, $rows);
    }

    private function exists(string $relation): bool
    {
        return $this->db->scalar('SELECT to_regclass(?)::text', [$relation]) !== null;
    }

    private function partitionName(string $table, CarbonImmutable $month): string
    {
        return $table.'_p'.$month->format('Ym');
    }

    private function assertIdentifier(string $table): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]{0,50}$/', $table) !== 1) {
            throw new InvalidArgumentException("Invalid table identifier [{$table}].");
        }
    }
}
