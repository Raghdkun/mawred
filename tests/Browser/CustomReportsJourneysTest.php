<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Models\ReportSchedule;
use App\Domain\Reports\Models\SavedReport;
use Illuminate\Support\Facades\Storage;

/*
| Phase 8b journeys (EN + AR, axe): build and save a custom report, share it, schedule a report,
| and manage the schedules.
*/
beforeEach(function (): void {
    Storage::fake('local');
    seedAccess();
    $this->branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->hr = userWithRoles('hr_admin');
    $this->hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->managerUser = userWithRoles('branch_manager');
    $this->manager = Employee::factory()->placedIn($this->branch)->create(['user_id' => $this->managerUser->id, 'name_en' => 'Maha Manager', 'name_ar' => 'مها المديرة']);

    foreach ([['Front of house', 'الصالة', 5], ['Kitchen', 'المطبخ', 3]] as [$en, $ar, $count]) {
        $department = Department::factory()->create(['name' => ['en' => $en, 'ar' => $ar]]);
        Employee::factory()->count($count)->placedIn($this->branch, $department)->create();
    }
});

it('builds, previews and saves a grouped report', function (string $locale): void {
    $this->hr->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->hr);

    visit('/reports/builder')->on()->macbook14()->wait(1)
        ->assertNoAccessibilityIssues()
        ->click('[data-dataset="people"]')->wait(0.5)
        ->click('[data-column="employee_name"]')
        ->click('[data-column="department"]')->wait(1.2)
        ->assertPresent('[data-builder-preview] [data-report-table]')
        ->click('[data-builder-step="group"]')->wait(0.3)
        ->click('[data-group-by]')->wait(0.3)
        ->click('[role="option"]:nth-child(5)')->wait(1.2)
        ->assertPresent('[data-builder-preview] [data-report-chart]')
        ->click('[data-builder-step="visualize"]')->wait(0.3)
        ->type('[data-report-name]', 'People by department')->wait(0.5)
        ->assertNoAccessibilityIssues()
        ->click('[data-save-report]')->wait(1.5)
        ->assertPresent('[data-report-result]')
        ->assertPresent('[data-share-report]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(SavedReport::query()->sole()->definition['groupBy'])->not->toBeNull();
})->with(['en', 'ar']);

it('shares a saved report with a role', function (string $locale): void {
    $this->hr->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->hr);
    $this->post(route('reports.saved.store'), ['name' => 'Team list', 'description' => null, 'definition' => ['dataset' => 'people', 'columns' => ['employee_name', 'department']]]);
    $saved = SavedReport::query()->sole();

    visit("/reports/{$saved->reportKey()}")->on()->macbook14()->wait(1.2)
        ->click('[data-share-report]')->wait(0.5)
        ->assertNoAccessibilityIssues()
        ->click('[data-share-role="branch_manager"]')
        ->click('[data-save-sharing]')->wait(1.2)
        ->assertNoJavaScriptErrors();

    expect($saved->shares()->count())->toBe(1);
})->with(['en', 'ar']);

it('schedules a report and manages it', function (string $locale): void {
    $this->managerUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->managerUser);

    visit('/reports/people.movements')->on()->macbook14()->wait(1.2)
        ->click('[data-schedule-report]')->wait(0.5)
        ->assertNoAccessibilityIssues()
        ->click('[data-save-schedule]')->wait(1.5)
        ->assertPresent('[data-schedule]')
        ->assertNoAccessibilityIssues()
        ->click('[data-toggle-schedule]')->wait(1)
        ->assertNoJavaScriptErrors();

    $schedule = ReportSchedule::query()->sole();
    expect($schedule->params)->toBe(['range' => 'last_month'])->and($schedule->paused_at)->not->toBeNull();

    visit('/reports/schedules')->on()->mobile()->wait(1)->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

/**
 * Screens for the visual check (handoff 37) at 390 / 834 / 1440, written to
 * tests/Browser/Screenshots; run with SCREENSHOTS=1.
 */
it('captures the builder and schedules screens', function (string $locale): void {
    $this->hr->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->hr);
    $this->post(route('reports.saved.store'), ['name' => $locale === 'ar' ? 'الموظفون حسب القسم' : 'People by department', 'description' => null, 'definition' => ['dataset' => 'people', 'groupBy' => 'department', 'measure' => 'count', 'chart' => 'bar', 'sort' => 'measure', 'direction' => 'desc']]);
    $saved = SavedReport::query()->sole();
    $this->post(route('reports.schedules.store'), ['report' => 'people.headcount', 'format' => 'xlsx', 'frequency' => 'weekly', 'weekday' => 7, 'monthDay' => null, 'hour' => 7, 'params' => []]);

    visit("/reports/builder/{$saved->ulid}")->on()->macbook14()->wait(2)->screenshot(true, "reports8b-builder-{$locale}-1440");
    visit("/reports/builder/{$saved->ulid}")->on()->macbook14()->inDarkMode()->wait(2)->screenshot(true, "reports8b-builder-{$locale}-dark");
    visit('/reports/builder')->on()->mobile()->wait(1)->screenshot(true, "reports8b-builder-{$locale}-390");

    foreach (['library' => '/reports', 'saved' => "/reports/{$saved->reportKey()}", 'schedules' => '/reports/schedules'] as $name => $path) {
        visit($path)->on()->mobile()->wait(1.5)->screenshot(true, "reports8b-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1.5)->screenshot(true, "reports8b-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1.5)->screenshot(true, "reports8b-{$name}-{$locale}-1440");
    }
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
