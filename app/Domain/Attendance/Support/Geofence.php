<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

use App\Domain\Attendance\Enums\GeofenceResult;

/**
 * Whether a reported position is inside a branch's circle (D26). The phone's accuracy counts in
 * the person's favour, capped so a vague fix can't stretch the area too far. Pure.
 */
final class Geofence
{
    private const int MAX_ACCURACY_ALLOWANCE_M = 100;

    public static function check(?float $branchLat, ?float $branchLng, ?int $radiusM, ?float $lat, ?float $lng, ?int $accuracyM): GeofenceResult
    {
        if ($branchLat === null || $branchLng === null || $radiusM === null || $lat === null || $lng === null) {
            return GeofenceResult::Unknown;
        }

        $allowance = min(max(0, $accuracyM ?? 0), self::MAX_ACCURACY_ALLOWANCE_M);

        return self::distance($branchLat, $branchLng, $lat, $lng) <= $radiusM + $allowance ? GeofenceResult::Inside : GeofenceResult::Outside;
    }

    /** Great-circle distance in metres (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6_371_000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
