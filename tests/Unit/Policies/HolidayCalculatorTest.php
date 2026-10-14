<?php

declare(strict_types=1);

use App\Domain\Policies\Enums\HolidayType;
use App\Domain\Policies\Enums\ObservedRule;
use App\Domain\Policies\Support\Calculators\HolidayCalculator;
use App\Domain\Policies\Support\Calculators\WorkingDays;
use App\Domain\Policies\Support\HolidayOccurrence;
use App\Domain\Policies\Support\HolidayPresets;
use App\Domain\Policies\Support\HolidayRuleSpec;

const US_WEEKEND = [6, 7];
const SA_WEEKEND = [5, 6];

/**
 * @param  array<string, mixed>  $attributes
 */
function rule(array $attributes): HolidayRuleSpec
{
    static $n = 0;

    return new HolidayRuleSpec(...[
        'id' => 'R'.++$n, 'calendarId' => 1, 'name' => ['en' => 'Holiday'], ...$attributes,
        'type' => HolidayType::from($attributes['type']),
        'observed' => ObservedRule::from($attributes['observed'] ?? 'none'),
    ]);
}

/**
 * @return list<HolidayRuleSpec>
 */
function usFederal(): array
{
    return array_map(rule(...), HolidayPresets::all()[HolidayPresets::US_FEDERAL]['rules']);
}

/**
 * @param  list<HolidayOccurrence>  $occurrences
 * @return array<string, string> observed date => English name
 */
function observed(array $occurrences): array
{
    return array_combine(array_map(fn (HolidayOccurrence $o): string => $o->observedOn, $occurrences), array_map(fn (HolidayOccurrence $o): string => $o->name['en'], $occurrences));
}

it('computes the 2026 US federal calendar, shifting Saturday to Friday', function (): void {
    expect(array_keys(observed(HolidayCalculator::between(usFederal(), '2026-01-01', '2026-12-31', US_WEEKEND))))->toBe([
        '2026-01-01', '2026-01-19', '2026-02-16', '2026-05-25', '2026-06-19', '2026-07-03',
        '2026-09-07', '2026-10-12', '2026-11-11', '2026-11-26', '2026-12-25',
    ]);
});

it('shifts across New Year and moves Sunday holidays to Monday', function (): void {
    $days = observed(HolidayCalculator::between(usFederal(), '2027-01-01', '2027-12-31', US_WEEKEND));

    expect($days)->toHaveKey('2027-07-05')
        ->and($days['2027-12-24'])->toBe('Christmas Day')
        ->and($days['2027-12-31'])->toBe("New Year's Day")
        ->and(observed(HolidayCalculator::between(usFederal(), '2028-01-01', '2028-01-31', US_WEEKEND)))->not->toHaveKey('2028-01-01');
});

it('respects year bounds, leap days and the last weekday of a month', function (): void {
    expect(HolidayCalculator::forYear(rule(['type' => 'fixed', 'month' => 6, 'day' => 19, 'fromYear' => 2021]), 2020, US_WEEKEND))->toBe([])
        ->and(HolidayCalculator::forYear(rule(['type' => 'fixed', 'month' => 2, 'day' => 29]), 2027, US_WEEKEND))->toBe([])
        ->and(HolidayCalculator::forYear(rule(['type' => 'fixed', 'month' => 2, 'day' => 29]), 2028, US_WEEKEND)[0]->date)->toBe('2028-02-29')
        ->and(HolidayCalculator::forYear(rule(['type' => 'nth_weekday', 'month' => 5, 'weekday' => 1, 'nth' => 5]), 2026, US_WEEKEND))->toBe([])
        ->and(HolidayCalculator::forYear(rule(['type' => 'nth_weekday', 'month' => 8, 'weekday' => 1, 'nth' => 5]), 2026, US_WEEKEND)[0]->date)->toBe('2026-08-31');
});

it('uses the branch weekend for shifts and keeps multi-day holidays on distinct days', function (string $date, string $mode, array $expected): void {
    $occurrences = HolidayCalculator::forYear(rule(['type' => 'one_off', 'date' => $date, 'durationDays' => 3, 'observed' => $mode]), 2026, SA_WEEKEND);

    expect(array_map(fn (HolidayOccurrence $o): string => $o->observedOn, $occurrences))->toBe($expected)
        ->and(array_column(array_map(fn (HolidayOccurrence $o): array => ['day' => $o->day], $occurrences), 'day'))->toBe([1, 2, 3]);
})->with([
    'no shift' => ['2026-03-20', 'none', ['2026-03-20', '2026-03-21', '2026-03-22']],
    'nearest' => ['2026-03-20', 'nearest_weekday', ['2026-03-19', '2026-03-23', '2026-03-22']],
    'next' => ['2026-03-20', 'next_working_day', ['2026-03-23', '2026-03-24', '2026-03-22']],
    'previous' => ['2026-03-20', 'previous_working_day', ['2026-03-19', '2026-03-18', '2026-03-22']],
]);

it('is pure: the same input gives the same output', function (): void {
    $rules = usFederal();

    expect(HolidayCalculator::between($rules, '2026-01-01', '2027-12-31', US_WEEKEND))
        ->toEqual(HolidayCalculator::between($rules, '2026-01-01', '2027-12-31', US_WEEKEND));
});

it('counts working days without weekends or holidays', function (): void {
    expect(WorkingDays::count('2026-10-11', '2026-10-17', SA_WEEKEND))->toBe(5)
        ->and(WorkingDays::count('2026-10-11', '2026-10-17', SA_WEEKEND, ['2026-10-13', '2026-10-16']))->toBe(4)
        ->and(WorkingDays::between('2026-10-15', '2026-10-18', SA_WEEKEND))->toBe(['2026-10-15', '2026-10-18'])
        ->and(WorkingDays::count('2026-10-18', '2026-10-17', SA_WEEKEND))->toBe(0);
});
