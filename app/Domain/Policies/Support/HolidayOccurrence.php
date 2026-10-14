<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

/**
 * One day off produced by a holiday rule: the calendar date and the day it is observed on (they
 * differ when a weekend shift applies). A multi-day holiday yields one occurrence per day.
 */
final readonly class HolidayOccurrence
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public int $calendarId,
        public string $ruleId,
        public array $name,
        public string $date,
        public string $observedOn,
        public bool $hijriBased,
        public int $day = 1,
        public int $days = 1,
    ) {}

    public function isShifted(): bool
    {
        return $this->date !== $this->observedOn;
    }
}
