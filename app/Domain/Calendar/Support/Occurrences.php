<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Calendar\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * The concrete occurrences of the events some people are on, in a range: single events, series
 * expanded (D78) minus skipped dates, with an override replacing the occurrence it was made for.
 */
final class Occurrences
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, list<Occurrence>> user id => occurrences, by start
     */
    public static function forUsers(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var Collection<int, CalendarEvent> $events */
        $events = CalendarEvent::query()
            ->with(['participants', 'organizer'])
            ->whereNull('cancelled_at')
            ->whereHas('participants', fn (Builder $q) => $q->whereIn('user_id', $userIds))
            ->where('starts_at', '<', $to)
            ->where(fn (Builder $q) => $q->whereNotNull('recurrence')->orWhere('ends_at', '>', $from))
            ->get();

        $replaced = [];

        foreach ($events->whereNotNull('series_id') as $override) {
            if ($override->original_start !== null) {
                $replaced[$override->series_id.'@'.$override->original_start->getTimestamp()] = true;
            }
        }

        $byUser = [];

        foreach ($events as $event) {
            foreach (self::expand($event, $from, $to) as $occurrence) {
                if ($event->series_id === null && isset($replaced[$event->id.'@'.$occurrence->start->getTimestamp()])) {
                    continue;
                }

                foreach ($event->participants as $participant) {
                    if (in_array($participant->user_id, $userIds, true)) {
                        $byUser[$participant->user_id][] = $occurrence;
                    }
                }
            }
        }

        foreach ($byUser as $userId => $list) {
            usort($list, fn (Occurrence $a, Occurrence $b): int => $a->start <=> $b->start);
            $byUser[$userId] = $list;
        }

        return $byUser;
    }

    /**
     * @return list<Occurrence>
     */
    public static function expand(CalendarEvent $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $duration = $event->durationSeconds();

        if ($event->recurrence === null || $event->series_id !== null) {
            return $event->starts_at->lessThan($to) && $event->ends_at->greaterThan($from)
                ? [new Occurrence($event, $event->starts_at, $event->ends_at)]
                : [];
        }

        return array_map(
            fn (CarbonImmutable $start): Occurrence => new Occurrence($event, $start, $start->addSeconds($duration)),
            Recurrence::occurrences($event->recurrence, $event->starts_at, $event->timezone, $duration, $from, $to, $event->exdates ?? []),
        );
    }
}
