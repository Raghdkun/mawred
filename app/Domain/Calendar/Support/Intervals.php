<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

/**
 * Half-open [start, end) intervals in Unix seconds: merge, subtract and intersect. Pure.
 */
final class Intervals
{
    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    public static function merge(array $intervals): array
    {
        $intervals = array_values(array_filter($intervals, fn (array $i): bool => $i[1] > $i[0]));
        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $out = [];

        foreach ($intervals as $interval) {
            $last = count($out) - 1;

            if ($last >= 0 && $interval[0] <= $out[$last][1]) {
                $out[$last][1] = max($out[$last][1], $interval[1]);
            } else {
                $out[] = $interval;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $base
     * @param  list<array{0: int, 1: int}>  $minus
     * @return list<array{0: int, 1: int}>
     */
    public static function subtract(array $base, array $minus): array
    {
        $minus = self::merge($minus);
        $out = [];

        foreach (self::merge($base) as [$start, $end]) {
            $cursor = $start;

            foreach ($minus as [$from, $to]) {
                if ($to <= $cursor || $from >= $end) {
                    continue;
                }

                if ($from > $cursor) {
                    $out[] = [$cursor, $from];
                }

                $cursor = max($cursor, $to);
            }

            if ($cursor < $end) {
                $out[] = [$cursor, $end];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $a
     * @param  list<array{0: int, 1: int}>  $b
     * @return list<array{0: int, 1: int}>
     */
    public static function intersect(array $a, array $b): array
    {
        $out = [];

        foreach (self::merge($a) as [$aStart, $aEnd]) {
            foreach (self::merge($b) as [$bStart, $bEnd]) {
                $start = max($aStart, $bStart);
                $end = min($aEnd, $bEnd);

                if ($end > $start) {
                    $out[] = [$start, $end];
                }
            }
        }

        return self::merge($out);
    }
}
