<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Attendance\Enums\OvertimeStatus;

/**
 * The overtime that counts on a day (approved or automatic, never more than worked, the same rule
 * as the monthly sheet), split over the day's buckets in proportion.
 */
final class OvertimeMinutes
{
    public const array BUCKETS = ['workday', 'weekend', 'holiday', 'night'];

    /**
     * @param  array<array-key, mixed>|null  $buckets  `attendance_days.overtime_buckets`
     * @return array{counted: int, buckets: array{workday: int, weekend: int, holiday: int, night: int}, weighted: float}
     */
    public static function split(string $status, int $overtime, int $approved, ?array $buckets): array
    {
        $counted = in_array($status, [OvertimeStatus::Approved->value, OvertimeStatus::Automatic->value], true) ? min($overtime, $approved) : 0;
        $split = ['workday' => 0, 'weekend' => 0, 'holiday' => 0, 'night' => 0];
        $weighted = 0.0;

        if ($counted <= 0) {
            return ['counted' => 0, 'buckets' => $split, 'weighted' => 0.0];
        }

        $share = $overtime > 0 ? $counted / $overtime : 0.0;
        $given = 0;

        foreach ($buckets ?? [] as $key => $bucket) {
            if (! is_array($bucket) || ! in_array($key, self::BUCKETS, true)) {
                continue;
            }

            $minutes = (int) round((is_numeric($bucket['minutes'] ?? null) ? (int) $bucket['minutes'] : 0) * $share);
            $split = match ($key) {
                'weekend' => [...$split, 'weekend' => $split['weekend'] + $minutes],
                'holiday' => [...$split, 'holiday' => $split['holiday'] + $minutes],
                'night' => [...$split, 'night' => $split['night'] + $minutes],
                default => [...$split, 'workday' => $split['workday'] + $minutes],
            };
            $given += $minutes;
            $weighted += $minutes * (is_numeric($bucket['multiplier'] ?? null) ? (float) $bucket['multiplier'] : 1.0);
        }

        // Minutes without a bucket (or lost to rounding) count as plain workday overtime.
        if ($given < $counted) {
            $split['workday'] += $counted - $given;
            $weighted += $counted - $given;
        }

        return ['counted' => $counted, 'buckets' => $split, 'weighted' => round($weighted / 60, 2)];
    }
}
