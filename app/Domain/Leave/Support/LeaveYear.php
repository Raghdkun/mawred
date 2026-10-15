<?php

declare(strict_types=1);

namespace App\Domain\Leave\Support;

/** The leave year is the calendar year (D63). */
final class LeaveYear
{
    public static function of(string $date): int
    {
        return (int) substr($date, 0, 4);
    }

    public static function start(int $year): string
    {
        return sprintf('%04d-01-01', $year);
    }

    public static function end(int $year): string
    {
        return sprintf('%04d-12-31', $year);
    }
}
