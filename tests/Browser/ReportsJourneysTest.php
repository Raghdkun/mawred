<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Models\ReportRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/*
| Phase 8a journeys (EN + AR, axe): the library, a report with its chart and table, an export
| made on the queue and downloaded from My exports.
*/
beforeEach(function (): void {
    Storage::fake('local');
    seedAccess();
    $branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->managerUser = userWithRoles('branch_manager');
    Employee::factory()->placedIn($branch)->create(['user_id' => $this->managerUser->id, 'name_en' => 'Maha Manager', 'name_ar' => 'مها المديرة']);
    $today = CarbonImmutable::now()->startOfDay();

    foreach ([['Front of house', 'الصالة', 6], ['Kitchen', 'المطبخ', 4], ['Delivery', 'التوصيل', 3]] as $index => [$en, $ar, $people]) {
        $department = Department::factory()->create(['name' => ['en' => $en, 'ar' => $ar]]);

        foreach (range(1, $people) as $n) {
            $person = Employee::factory()->placedIn($branch, $department)->create(['name_en' => "{$en} {$n}", 'name_ar' => "{$ar} {$n}"]);

            if ($n <= 2) {
                AttendanceDay::query()->create([
                    'employee_id' => $person->id, 'branch_id' => $branch->id, 'date' => $today->subDays($n + $index)->toDateString(), 'schedule_source' => 'policy', 'status' => 'present',
                    'overtime_minutes' => 45 * $n, 'overtime_status' => 'approved', 'overtime_approved_minutes' => 45 * $n,
                    'overtime_buckets' => [$index === 2 ? 'weekend' : 'workday' => ['multiplier' => 1.5, 'minutes' => 45 * $n]],
                ]);
            }
        }
    }
});

it('opens a report and exports it to My exports', function (string $locale): void {
    $this->managerUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->managerUser);

    visit('/reports')->on()->macbook14()->wait(1)
        ->assertNoAccessibilityIssues()
        ->click('[data-report="people.headcount"]')->wait(1.5)
        ->assertPresent('[data-report-result]')
        ->assertPresent('[data-report-chart]')
        ->assertPresent('[data-report-table]')
        ->assertNoAccessibilityIssues()
        ->click('[data-export]')->wait(0.5)
        ->click('[data-export-format="xlsx"]')->wait(1.5)
        ->assertPresent('[data-download]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(ReportRun::query()->sole()->status)->toBe(RunStatus::Ready);
})->with(['en', 'ar']);

it('filters a report and shows rows as cards on a phone', function (string $locale): void {
    $this->managerUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->managerUser);
    $today = CarbonImmutable::now();

    visit('/reports/time.overtime')->on()->mobile()->wait(1.5)
        ->assertPresent('[data-report-cards]')
        ->assertNoAccessibilityIssues()
        ->fill('[data-filter="from"]', $today->subDays(30)->toDateString())
        ->click('[data-apply-filters]')->wait(1.5)
        ->assertPresent('[data-kpis]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

/**
 * Screens for the visual check (handoff 36) at 390 / 834 / 1440, written to
 * tests/Browser/Screenshots; run with SCREENSHOTS=1.
 */
it('captures the reports screens', function (string $locale): void {
    $this->managerUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->managerUser);
    $this->post(route('reports.exports.store', 'people.headcount'), ['format' => 'pdf']);

    foreach (['library' => '/reports', 'headcount' => '/reports/people.headcount', 'overtime' => '/reports/time.overtime?from='.CarbonImmutable::now()->subDays(30)->toDateString(), 'exports' => '/reports/exports'] as $name => $path) {
        visit($path)->on()->mobile()->wait(1.5)->screenshot(true, "reports8a-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1.5)->screenshot(true, "reports8a-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1.5)->screenshot(true, "reports8a-{$name}-{$locale}-1440");
    }

    visit('/reports/people.headcount')->on()->macbook14()->inDarkMode()->wait(1.5)->screenshot(true, "reports8a-headcount-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
