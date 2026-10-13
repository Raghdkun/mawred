<?php

declare(strict_types=1);

use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('resolves today per timezone, not per server', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-13 22:30:00', 'UTC'));
    $clock = new LocalClock;

    expect($clock->today('UTC'))->toBe('2026-10-13')
        ->and($clock->today('Asia/Riyadh'))->toBe('2026-10-14')
        ->and($clock->today('America/New_York'))->toBe('2026-10-13');
});

it('maps local days to UTC boundaries across DST', function (): void {
    $clock = new LocalClock;

    // US DST ends on 2026-11-01: that local day is 25 hours long.
    $start = $clock->startOfLocalDay('2026-11-01', 'America/New_York');
    $end = $clock->endOfLocalDay('2026-11-01', 'America/New_York');

    expect($start->toIso8601String())->toBe('2026-11-01T04:00:00+00:00')
        ->and($end->toIso8601String())->toBe('2026-11-02T05:00:00+00:00');
});

it('assigns an instant to its local calendar date', function (): void {
    $clock = new LocalClock;

    expect($clock->toLocalDate(CarbonImmutable::parse('2026-10-13T21:15:00Z'), 'Asia/Dubai'))->toBe('2026-10-14');
});
