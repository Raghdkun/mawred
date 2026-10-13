<?php

declare(strict_types=1);

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Converts between UTC instants and calendar dates in a given timezone (ADR-0004).
 *
 * Domain code never calls `now()->toDateString()`: "today" only exists relative to a
 * timezone (a branch's or a user's). BranchClock (Phase 1) builds on this.
 */
final class LocalClock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /** The current local calendar date (Y-m-d) in the timezone. */
    public function today(string $timezone): string
    {
        return $this->now()->setTimezone($timezone)->toDateString();
    }

    /** The local calendar date on which a UTC instant falls. */
    public function toLocalDate(DateTimeInterface $instant, string $timezone): string
    {
        return CarbonImmutable::instance($instant)->setTimezone($timezone)->toDateString();
    }

    /** The UTC instant at which a local calendar date starts. */
    public function startOfLocalDay(string $date, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $timezone)->startOfDay()->utc();
    }

    /** The UTC instant at which a local calendar date ends (exclusive). */
    public function endOfLocalDay(string $date, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $timezone)->addDay()->startOfDay()->utc();
    }

    /**
     * A local wall-clock time on a local date, as a UTC instant. Overnight spans belong to the
     * date they start on, so callers add a day to the end time when it is before the start.
     */
    public function atLocalTime(string $date, string $time, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' '.$time, $timezone)->utc();
    }
}
