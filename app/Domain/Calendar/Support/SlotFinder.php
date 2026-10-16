<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

/**
 * Suggested times (handoff 27): where every required person is free for the whole duration,
 * starting on a step boundary (e.g. every 30 minutes). Pure.
 */
final class SlotFinder
{
    /**
     * @param  list<list<array{0: int, 1: int}>>  $freeByPerson  each person's free intervals (Unix seconds)
     * @return list<array{0: int, 1: int}> slot [start, end)
     */
    public static function find(array $freeByPerson, int $durationSeconds, int $stepSeconds, int $limit = 12, int $notBefore = 0): array
    {
        if ($freeByPerson === [] || $durationSeconds <= 0) {
            return [];
        }

        $common = array_shift($freeByPerson);

        foreach ($freeByPerson as $free) {
            $common = Intervals::intersect($common, $free);
        }

        $step = max(60, $stepSeconds);
        $slots = [];

        foreach (Intervals::merge($common) as [$start, $end]) {
            $cursor = (int) (ceil(max($start, $notBefore) / $step) * $step);

            for (; $cursor + $durationSeconds <= $end; $cursor += $step) {
                $slots[] = [$cursor, $cursor + $durationSeconds];

                if (count($slots) >= $limit) {
                    return $slots;
                }
            }
        }

        return $slots;
    }
}
