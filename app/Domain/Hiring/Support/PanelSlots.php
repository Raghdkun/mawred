<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Support\Availability;
use App\Domain\Calendar\Support\SlotFinder;
use App\Domain\Hiring\Models\Interview;
use Carbon\CarbonImmutable;

/**
 * Times the whole panel is free (D89), for the candidate's booking page: working time minus
 * meetings and leave (the free/busy engine, D76), from tomorrow for two weeks, on half-hour steps.
 */
final readonly class PanelSlots
{
    public const int LIMIT = 40;

    public function __construct(private Availability $availability) {}

    /**
     * @return list<array{0: int, 1: int}> slot [start, end) as Unix seconds
     */
    public function for(Interview $interview, CarbonImmutable $now): array
    {
        $panel = $interview->panel()->get()->all();

        if ($panel === []) {
            return [];
        }

        /** @var User $viewer */
        $viewer = $panel[0];
        $from = $now->setTimezone($interview->timezone)->addDay()->startOfDay();
        $to = $from->addDays(Interview::BOOKING_DAYS);
        $rows = $this->availability->for($viewer, array_values($panel), $from, $to);

        return SlotFinder::find(
            array_map(fn (array $row): array => $row['free'], $rows),
            $interview->duration_minutes * 60,
            30 * 60,
            self::LIMIT,
            $now->addHours(12)->getTimestamp(),
        );
    }

    /** Whether the panel is still free for this slot (it may have filled since the page loaded). */
    public function stillFree(Interview $interview, int $start, CarbonImmutable $now): bool
    {
        foreach ($this->for($interview, $now) as [$slotStart]) {
            if ($slotStart === $start) {
                return true;
            }
        }

        return false;
    }
}
