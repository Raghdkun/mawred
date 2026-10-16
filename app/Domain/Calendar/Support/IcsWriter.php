<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use Carbon\CarbonImmutable;

/**
 * `.ics` (RFC 5545) for one event: UTC times, the repeat rule and skipped dates, attendees and
 * their replies, SEQUENCE for updates and METHOD:CANCEL when it's off. Pure.
 */
final class IcsWriter
{
    /**
     * @param  array{uid: string, title: string, description?: string|null, location?: string|null, starts: CarbonImmutable, ends: CarbonImmutable, organizer: array{name: string, email: string|null}, attendees: list<array{name: string, email: string|null, response: string}>, sequence: int, cancelled: bool, recurrence?: array{freq: string, interval?: int, byweekday?: list<int>, until?: string|null, count?: int|null}|null, exdates?: list<string>, url?: string|null, stamp: CarbonImmutable}  $event
     */
    public static function write(array $event): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mawred//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($event['cancelled'] ? 'CANCEL' : 'REQUEST'),
            'BEGIN:VEVENT',
            'UID:'.$event['uid'],
            'DTSTAMP:'.self::utc($event['stamp']),
            'DTSTART:'.self::utc($event['starts']),
            'DTEND:'.self::utc($event['ends']),
            'SEQUENCE:'.$event['sequence'],
            'STATUS:'.($event['cancelled'] ? 'CANCELLED' : 'CONFIRMED'),
            'SUMMARY:'.self::text($event['title']),
        ];

        if (($event['description'] ?? null) !== null && $event['description'] !== '') {
            $lines[] = 'DESCRIPTION:'.self::text($event['description']);
        }

        if (($event['location'] ?? null) !== null && $event['location'] !== '') {
            $lines[] = 'LOCATION:'.self::text($event['location']);
        }

        if (($event['url'] ?? null) !== null) {
            $lines[] = 'URL:'.$event['url'];
        }

        $lines[] = 'ORGANIZER;CN='.self::param($event['organizer']['name']).':mailto:'.($event['organizer']['email'] ?? 'noreply@invalid');

        foreach ($event['attendees'] as $attendee) {
            $lines[] = 'ATTENDEE;CN='.self::param($attendee['name']).';PARTSTAT='.self::partstat($attendee['response']).':mailto:'.($attendee['email'] ?? 'noreply@invalid');
        }

        if (($event['recurrence'] ?? null) !== null) {
            $lines[] = 'RRULE:'.self::rrule($event['recurrence']);
        }

        foreach ($event['exdates'] ?? [] as $exdate) {
            $lines[] = 'EXDATE:'.self::utc(CarbonImmutable::parse($exdate));
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * @param  array{freq: string, interval?: int, byweekday?: list<int>, until?: string|null, count?: int|null}  $rule
     */
    public static function rrule(array $rule): string
    {
        $days = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
        $parts = ['FREQ='.strtoupper($rule['freq'])];

        if (($rule['interval'] ?? 1) > 1) {
            $parts[] = 'INTERVAL='.$rule['interval'];
        }

        if ($rule['freq'] === 'weekly' && ($rule['byweekday'] ?? []) !== []) {
            $parts[] = 'BYDAY='.implode(',', array_map(fn (int $d): string => $days[$d - 1] ?? 'MO', $rule['byweekday']));
        }

        if (($rule['until'] ?? null) !== null) {
            $parts[] = 'UNTIL='.str_replace('-', '', $rule['until']).'T235959Z';
        } elseif (($rule['count'] ?? null) !== null) {
            $parts[] = 'COUNT='.$rule['count'];
        }

        return implode(';', $parts);
    }

    private static function utc(CarbonImmutable $instant): string
    {
        return $instant->utc()->format('Ymd\THis\Z');
    }

    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    private static function param(string $value): string
    {
        return '"'.str_replace(['"', "\r", "\n"], ["'", ' ', ' '], $value).'"';
    }

    private static function partstat(string $response): string
    {
        return match ($response) {
            'accepted' => 'ACCEPTED',
            'tentative' => 'TENTATIVE',
            'declined' => 'DECLINED',
            default => 'NEEDS-ACTION',
        };
    }

    /** Folds a content line at 75 octets without splitting a UTF-8 character. */
    private static function fold(string $line): string
    {
        $out = '';
        $current = '';

        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ").$current;
                $current = '';
            }

            $current .= $char;
        }

        return $out === '' ? $current : $out."\r\n ".$current;
    }
}
