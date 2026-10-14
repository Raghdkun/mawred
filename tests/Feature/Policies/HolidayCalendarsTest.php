<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Actions\DeleteHolidayCalendar;
use App\Domain\Policies\Actions\ImportHolidayDates;
use App\Domain\Policies\Actions\InstallHolidayPreset;
use App\Domain\Policies\Actions\SaveHolidayCalendar;
use App\Domain\Policies\Actions\SaveHolidayRule;
use App\Domain\Policies\Data\HolidayCalendarInputData;
use App\Domain\Policies\Data\HolidayRuleInputData;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\HolidayRule;
use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\HolidayPresets;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

beforeEach(function (): void {
    $this->actor = Actor::system('test');
    $this->newYork = Branch::factory()->create();
    $this->riyadh = Branch::factory()->riyadh()->create();
});

function calendar(string $code, array $branches, bool $active = true): HolidayCalendar
{
    return resolve(SaveHolidayCalendar::class)->handle(Actor::system('test'), HolidayCalendarInputData::from([
        'code' => $code, 'name' => ['en' => $code], 'isActive' => $active, 'branchIds' => array_map(fn (Branch $b): string => $b->ulid, $branches),
    ]));
}

function holiday(HolidayCalendar $calendar, array $input): HolidayRule
{
    return resolve(SaveHolidayRule::class)->handle($calendar, HolidayRuleInputData::from(['name' => ['en' => 'Day off'], ...$input]));
}

function holidays(Branch $branch, string $from, string $to): array
{
    app()->forgetScopedInstances();

    return resolve(BranchHolidays::class)->dates($branch, $from, $to);
}

it('gives each branch the holidays of every active calendar assigned to it, shifted by its own weekend', function (): void {
    $shared = calendar('SHARED', [$this->newYork, $this->riyadh]);
    holiday($shared, ['type' => 'fixed', 'month' => 10, 'day' => 16, 'observed' => 'nearest_weekday']);
    holiday(calendar('RIYADH', [$this->riyadh]), ['type' => 'one_off', 'date' => '2026-10-20']);
    holiday(calendar('PAUSED', [$this->riyadh], active: false), ['type' => 'one_off', 'date' => '2026-10-21']);

    // 16 Oct 2026 is a Friday: a working day in New York, the weekend in Riyadh (moved to Thursday).
    expect(holidays($this->newYork, '2026-10-01', '2026-10-31'))->toBe(['2026-10-16'])
        ->and(holidays($this->riyadh, '2026-10-01', '2026-10-31'))->toBe(['2026-10-15', '2026-10-20']);
});

it('counts a day once when two calendars share it, and stops after archiving', function (): void {
    holiday(calendar('A', [$this->newYork]), ['type' => 'one_off', 'date' => '2026-10-14']);
    $b = calendar('B', [$this->newYork]);
    holiday($b, ['type' => 'one_off', 'date' => '2026-10-14']);

    expect(resolve(BranchHolidays::class)->workingDays($this->newYork, '2026-10-12', '2026-10-16'))->toBe(4);

    resolve(DeleteHolidayCalendar::class)->handle($b);
    expect(holidays($this->newYork, '2026-10-01', '2026-10-31'))->toBe(['2026-10-14'])
        ->and($b->branches()->count())->toBe(0);
});

it('keeps only the fields a rule type uses and rejects impossible days', function (): void {
    $calendar = calendar('X', []);
    $rule = holiday($calendar, ['type' => 'fixed', 'month' => 3, 'day' => 8, 'fromYear' => 2020]);
    holiday($calendar, ['type' => 'one_off', 'date' => '2026-03-08', 'month' => 3, 'day' => 8, 'fromYear' => 2020]);

    $rule = resolve(SaveHolidayRule::class)->handle($calendar, HolidayRuleInputData::from(['name' => ['en' => 'X'], 'type' => 'one_off', 'date' => '2026-03-09', 'day' => 9]), $rule);
    expect($rule->fresh())->month->toBeNull()->day->toBeNull()->from_year->toBeNull()->date->toDateString()->toBe('2026-03-09');

    expect(fn () => holiday($calendar, ['type' => 'fixed', 'month' => 2, 'day' => 30]))->toThrow(DomainRuleViolation::class);
});

it('installs the US federal preset once', function (): void {
    $calendar = resolve(InstallHolidayPreset::class)->handle($this->actor, HolidayPresets::US_FEDERAL, [$this->newYork->ulid]);
    $again = resolve(InstallHolidayPreset::class)->handle($this->actor, HolidayPresets::US_FEDERAL);

    expect($again->is($calendar))->toBeTrue()
        ->and($calendar->rules()->count())->toBe(11)
        ->and(holidays($this->newYork, '2026-07-01', '2026-07-31'))->toBe(['2026-07-03']);
});

it('imports one-off dates from CSV, skipping ones already there', function (): void {
    $calendar = calendar('SA', [$this->riyadh]);
    $path = tempnam(sys_get_temp_dir(), 'hol');
    file_put_contents($path, "date,name_en,name_ar,days,hijri_based\n2026-03-20,Eid al-Fitr,عيد الفطر,3,yes\n2026-05-27,Eid al-Adha,عيد الأضحى,4,yes\n\n");

    expect(resolve(ImportHolidayDates::class)->handle($calendar, $path, 'csv'))->toBe(['imported' => 2, 'skipped' => 0])
        ->and(resolve(ImportHolidayDates::class)->handle($calendar, $path, 'csv'))->toBe(['imported' => 0, 'skipped' => 2]);

    $eid = $calendar->rules()->where('name->en', 'Eid al-Fitr')->sole();
    expect($eid)->duration_days->toBe(3)->hijri_based->toBeTrue()->getTranslation('name', 'ar')->toBe('عيد الفطر');
});

it('refuses the whole CSV when a row is invalid', function (): void {
    $calendar = calendar('SA', [$this->riyadh]);
    $path = tempnam(sys_get_temp_dir(), 'hol');
    file_put_contents($path, "date,name_en\n2026-03-20,Eid\n20/03/2026,Bad date\n");

    expect(fn () => resolve(ImportHolidayDates::class)->handle($calendar, $path, 'csv'))
        ->toThrow(fn (DomainRuleViolation $e) => expect($e->details)->toHaveKey('rows.3'))
        ->and($calendar->rules()->count())->toBe(0);
});
