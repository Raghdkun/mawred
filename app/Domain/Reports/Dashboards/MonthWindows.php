<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

use Carbon\CarbonImmutable;

/**
 * The last 12 months for a sparkline, oldest first: each month's first day and its last day
 * (today for the current month). Column aliases are fixed so raw SQL stays a code literal.
 */
final class MonthWindows
{
    public const array ALIASES = ['m0', 'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11'];

    public const array SECOND = ['n0', 'n1', 'n2', 'n3', 'n4', 'n5', 'n6', 'n7', 'n8', 'n9', 'n10', 'n11'];

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function last12(string $today): array
    {
        $day = CarbonImmutable::parse($today);
        $windows = [];

        for ($back = 11; $back >= 0; $back--) {
            $start = $day->startOfMonth()->subMonthsNoOverflow($back);
            $windows[] = [$start->toDateString(), $back === 0 ? $day->toDateString() : $start->endOfMonth()->toDateString()];
        }

        return $windows;
    }

    /**
     * Twelve numbers from a row read with `ALIASES` (or `SECOND`).
     *
     * @param  array<array-key, mixed>  $row
     * @param  list<string>  $aliases
     * @return list<int>
     */
    public static function counts(array $row, array $aliases = self::ALIASES): array
    {
        return array_map(fn (string $alias): int => is_numeric($row[$alias] ?? null) ? (int) $row[$alias] : 0, $aliases);
    }
}
