<?php

declare(strict_types=1);

use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Support\IcsWriter;
use App\Domain\Calendar\Support\Intervals;
use App\Domain\Calendar\Support\Recurrence;
use App\Domain\Calendar\Support\ReplyTokens;
use App\Domain\Calendar\Support\SlotFinder;
use Carbon\CarbonImmutable;

/*
| Phase 5b-2 pure pieces: repeats (D78), free-time maths, suggested slots, reply tokens and .ics.
*/
function starts(array $occurrences, string $tz = 'Asia/Riyadh'): array
{
    return array_map(fn (CarbonImmutable $s): string => $s->setTimezone($tz)->format('Y-m-d H:i'), $occurrences);
}

it('repeats daily, weekly on chosen days and monthly, with until, count and skipped dates', function (): void {
    $first = CarbonImmutable::parse('2026-03-02 09:00', 'Asia/Riyadh');
    $range = [CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-06-01')];

    expect(starts(Recurrence::occurrences(['freq' => 'daily', 'interval' => 2, 'count' => 3], $first, 'Asia/Riyadh', 1800, ...$range)))
        ->toBe(['2026-03-02 09:00', '2026-03-04 09:00', '2026-03-06 09:00']);

    expect(starts(Recurrence::occurrences(['freq' => 'weekly', 'byweekday' => [1, 3], 'until' => '2026-03-11'], $first, 'Asia/Riyadh', 1800, ...$range, exdates: ['2026-03-04T06:00:00Z'])))
        ->toBe(['2026-03-02 09:00', '2026-03-09 09:00', '2026-03-11 09:00']);

    $month = CarbonImmutable::parse('2026-01-31 10:00', 'Asia/Riyadh');
    expect(starts(Recurrence::occurrences(['freq' => 'monthly', 'count' => 4], $month, 'Asia/Riyadh', 1800, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'))))
        ->toBe(['2026-01-31 10:00', '2026-03-31 10:00', '2026-05-31 10:00', '2026-07-31 10:00']);
});

it('keeps the wall-clock time across a DST change and only returns the asked range', function (): void {
    $first = CarbonImmutable::parse('2026-03-02 09:00', 'Europe/London');
    $occurrences = Recurrence::occurrences(['freq' => 'weekly'], $first, 'Europe/London', 3600, CarbonImmutable::parse('2026-03-20'), CarbonImmutable::parse('2026-04-05'));

    expect(starts($occurrences, 'Europe/London'))->toBe(['2026-03-23 09:00', '2026-03-30 09:00'])
        ->and($occurrences[1]->format('H:i'))->toBe('08:00');
});

it('merges, subtracts and intersects intervals and finds common free slots', function (): void {
    expect(Intervals::merge([[5, 10], [0, 3], [2, 6]]))->toBe([[0, 10]])
        ->and(Intervals::subtract([[0, 100]], [[10, 20], [50, 60]]))->toBe([[0, 10], [20, 50], [60, 100]])
        ->and(Intervals::intersect([[0, 50]], [[40, 100]]))->toBe([[40, 50]]);

    $hour = 3600;
    $slots = SlotFinder::find([
        [[9 * $hour, 17 * $hour]],
        [[8 * $hour, 12 * $hour], [13 * $hour, 18 * $hour]],
    ], $hour, 1800, 3, notBefore: 10 * $hour + 600);

    expect($slots)->toBe([[10 * $hour + 1800, 11 * $hour + 1800], [11 * $hour, 12 * $hour], [13 * $hour, 14 * $hour]]);
});

it('signs one-tap replies and rejects tampered ones', function (): void {
    $token = ReplyTokens::make('01J00000000000000000000000', Response::Declined, 'key');

    expect(ReplyTokens::looksLikeOne($token))->toBeTrue()
        ->and(ReplyTokens::verify($token, 'key'))->toBe(['01J00000000000000000000000', Response::Declined])
        ->and(ReplyTokens::verify(str_replace('.d.', '.a.', $token), 'key'))->toBeNull()
        ->and(ReplyTokens::verify($token, 'other'))->toBeNull();
});

it('writes an .ics with the repeat rule, attendees and folded lines', function (): void {
    $ics = IcsWriter::write([
        'uid' => '01JEVENT@mawred',
        'title' => 'Weekly sync; planning, Q2',
        'description' => str_repeat('Long agenda line ', 10),
        'location' => 'Room 3',
        'starts' => CarbonImmutable::parse('2026-03-02 06:00:00'),
        'ends' => CarbonImmutable::parse('2026-03-02 06:30:00'),
        'organizer' => ['name' => 'Saud', 'email' => 'saud@example.test'],
        'attendees' => [['name' => 'Rami', 'email' => 'rami@example.test', 'response' => 'tentative']],
        'sequence' => 2,
        'cancelled' => false,
        'recurrence' => ['freq' => 'weekly', 'byweekday' => [1, 3], 'until' => '2026-04-01'],
        'exdates' => ['2026-03-04T06:00:00Z'],
        'stamp' => CarbonImmutable::parse('2026-03-01 00:00:00'),
    ]);

    expect($ics)->toContain("DTSTART:20260302T060000Z\r\n")
        ->toContain('SUMMARY:Weekly sync\; planning\, Q2')
        ->toContain('RRULE:FREQ=WEEKLY;BYDAY=MO,WE;UNTIL=20260401T235959Z')
        ->toContain('EXDATE:20260304T060000Z')
        ->toContain('PARTSTAT=TENTATIVE')
        ->toContain('SEQUENCE:2');

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});
