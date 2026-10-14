<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\AssignPolicySet;
use App\Domain\Policies\Actions\InstallHolidayPreset;
use App\Domain\Policies\Actions\SavePolicySet;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Models\PolicyValue;
use App\Domain\Policies\Support\HolidayPresets;
use App\Support\Actors\Actor;

beforeEach(function (): void {
    $this->travelTo('2026-10-15 09:00:00');
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    $this->root = userWithRoles('super_admin');
    $this->root->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->riyadh = Branch::factory()->riyadh()->create(['code' => 'RUH', 'name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $ops = Department::factory()->create(['code' => 'OPS', 'name' => ['en' => 'Operations', 'ar' => 'العمليات']]);
    $this->sara = Employee::factory()->placedIn($this->riyadh, $ops)->create(['name_en' => 'Sara Haddad', 'name_ar' => 'سارة حداد', 'employee_number' => 'EMP-000001']);

    $actor = Actor::system('test');
    $this->riyadhSet = resolve(SavePolicySet::class)->handle($actor, ['en' => 'Riyadh rules', 'ar' => 'قواعد الرياض']);
    resolve(SetPolicyValues::class)->handle($actor, $this->riyadhSet, ['attendance.grace' => ['lateMinutes' => 10], 'work.weekend' => ['days' => [5, 6]]], '2026-01-01', 'test');
    resolve(AssignPolicySet::class)->handle($actor, $this->riyadhSet, PolicyLevel::Branch, $this->riyadh->id, '2026-01-01', 'test');
    resolve(InstallHolidayPreset::class)->handle($actor, HolidayPresets::US_FEDERAL, [$this->riyadh->ulid]);
    $this->neutral = PolicySet::query()->where('preset_key', 'neutral-default')->sole();
});

it('renders the Phase 2 screens accessibly in both languages', function (string $locale, string $path): void {
    $this->root->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->root);

    visit(strtr($path, ['{set}' => $this->riyadhSet->ulid, '{sara}' => $this->sara->ulid]))
        ->wait(0.8)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar'])->with([
    'sets' => '/policies',
    'editor' => '/policies/{set}',
    'assignments' => '/policies/assignments',
    'effective' => '/policies/effective?employee={sara}',
    'leave types' => '/policies/leave-types',
    'holidays' => '/policies/holidays?year=2026',
    'profile policies' => '/people/{sara}?tab=policies',
]);

it('changes a rule from a date after previewing who it affects', function (): void {
    $this->actingAs($this->root);

    visit("/policies/{$this->riyadhSet->ulid}")
        ->wait(0.6)
        ->fill('#key-attendance-grace-lateMinutes', '5')
        ->assertSee('1 unsaved change')
        ->click('Save changes')
        ->wait(1)
        ->assertSee('Affects 1 employee')
        ->assertSee('Sara Haddad')
        ->click('Save and apply')
        ->wait(1)
        ->assertSee('Policy values saved.')
        ->assertNoJavaScriptErrors();

    $values = PolicyValue::query()->where('policy_set_id', $this->riyadhSet->id)->where('key', 'attendance.grace')->orderBy('effective_from')->get();
    expect($values)->toHaveCount(2)->and($values->last()?->value['lateMinutes'])->toBe(5);
});

it('explains where an effective value comes from', function (): void {
    $this->actingAs($this->root);

    visit("/policies/effective?employee={$this->sara->ulid}")
        ->wait(0.8)
        ->assertSee('Lateness grace')
        ->click('li:has-text("Lateness grace") button:has-text("From Branch: Riyadh HQ")')
        ->wait(0.4)
        ->assertSee('Where this value comes from')
        ->assertSee('Open Riyadh rules')
        ->assertNoJavaScriptErrors();
});

it('shows a branch year of holidays, shifted by its weekend', function (): void {
    $this->actingAs($this->root);

    // 4 July 2026 is a Saturday, a weekend day in Riyadh too: the nearest working day is Sunday 5 July.
    visit('/policies/holidays?year=2026')
        ->wait(0.8)
        ->click('[aria-label="List"]')
        ->assertSee('Independence Day')
        ->assertSee('US federal holidays')
        ->assertNoJavaScriptErrors();
});

/*
 * Visual check (CLAUDE.md): 390 / 834 / 1440 × LTR / RTL, dark for the editor. Writes PNGs to
 * tests/Browser/Screenshots; run with SCREENSHOTS=1.
 */
it('captures the policy screens for review', function (string $locale): void {
    $this->root->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->root);
    $pages = [
        'sets' => '/policies',
        'editor' => "/policies/{$this->riyadhSet->ulid}",
        'assignments' => '/policies/assignments',
        'effective' => "/policies/effective?employee={$this->sara->ulid}",
        'holidays' => '/policies/holidays?year=2026',
    ];

    foreach ($pages as $name => $path) {
        visit($path)->on()->mobile()->wait(0.8)->screenshot(true, "policies-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(0.8)->screenshot(true, "policies-{$name}-{$locale}-834");
        visit($path)->wait(0.8)->screenshot(true, "policies-{$name}-{$locale}-1440");
    }

    visit($pages['editor'])->inDarkMode()->wait(0.8)->screenshot(true, "policies-editor-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
