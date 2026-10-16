<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Calendar\Models\CalendarEvent;
use Carbon\CarbonImmutable;

/** One concrete occurrence of an event (a single event is its own only occurrence). */
final readonly class Occurrence
{
    public function __construct(
        public CalendarEvent $event,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /** Identifies the occurrence within its series (the start it was scheduled for). */
    public function key(): string
    {
        return $this->event->ulid.'@'.$this->start->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
