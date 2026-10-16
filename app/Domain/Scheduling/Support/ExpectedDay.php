<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\Policies\Enums\DayKind;

/**
 * What someone is expected to work on a branch-local date (D68). Times are minutes from that
 * date's local midnight; an end past 1440 runs into the next day.
 */
final readonly class ExpectedDay
{
    /**
     * @param  'shift'|'policy'|'none'  $source
     * @param  'leave'|'day_off'|'holiday'|'weekend'|null  $reason  why nothing is expected
     * @param  list<array{0: int, 1: int}>  $excused  approved hourly permissions [from, to)
     */
    public function __construct(
        public string $source,
        public ?string $reason,
        public ?int $start,
        public ?int $end,
        public int $paidMinutes,
        public DayKind $kind,
        public string $timezone,
        public array $excused = [],
    ) {}

    public function expectsWork(): bool
    {
        return $this->start !== null && $this->end !== null;
    }

    /** Start after an approved permission that covers it (coming in late with permission). */
    public function effectiveStart(): ?int
    {
        $start = $this->start;

        foreach ($this->excused as [$from, $to]) {
            if ($start !== null && $from <= $start && $to > $start) {
                $start = $to;
            }
        }

        return $start;
    }

    /** End before an approved permission that covers it (leaving early with permission). */
    public function effectiveEnd(): ?int
    {
        $end = $this->end;

        foreach ($this->excused as [$from, $to]) {
            if ($end !== null && $from < $end && $to >= $end) {
                $end = $from;
            }
        }

        return $end;
    }
}
