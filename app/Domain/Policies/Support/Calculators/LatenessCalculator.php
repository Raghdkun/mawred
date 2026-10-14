<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support\Calculators;

use App\Domain\Policies\Data\Values\GraceValue;

/**
 * Late arrival and early leave for one day, in minutes after the grace rules (D50). Pure.
 * A missing punch counts as nothing here; absence and missing punches are handled by attendance.
 */
final class LatenessCalculator
{
    /**
     * @return array{late: int, earlyLeave: int}
     */
    public static function forDay(GraceValue $grace, int $scheduledStart, int $scheduledEnd, ?int $firstIn, ?int $lastOut): array
    {
        return [
            'late' => self::count($firstIn === null ? 0 : $firstIn - $scheduledStart, $grace->lateMinutes, $grace->countFrom),
            'earlyLeave' => self::count($lastOut === null ? 0 : $scheduledEnd - $lastOut, $grace->earlyLeaveMinutes, $grace->countFrom),
        ];
    }

    private static function count(int $minutes, int $grace, string $countFrom): int
    {
        if ($minutes <= $grace) {
            return 0;
        }

        return $countFrom === 'grace_end' ? $minutes - $grace : $minutes;
    }
}
