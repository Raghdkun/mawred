<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Training\Actions\SeedTrainingDefaults;

/*
| D32 / Phase 9a: every main screen passes axe (including colour contrast) in dark mode, in both
| languages. The ★ screens also get a visual pass (screenshot sweeps per phase).
*/
beforeEach(function (): void {
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedTrainingDefaults::class)->handle();
    $branch = Branch::factory()->riyadh()->withGeofence()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->hr = userWithRoles('hr_admin');
    $this->hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Employee::factory()->placedIn($branch)->create(['user_id' => $this->hr->id, 'name_en' => 'Hala HR', 'name_ar' => 'هالة']);
    Employee::factory()->count(4)->placedIn($branch)->create();
    $this->branch = $branch;
});

it('passes axe in dark mode on the main screens', function (string $locale): void {
    $this->hr->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->hr);

    $paths = [
        '/dashboard', '/requests', '/approvals', '/schedule', '/calendar', '/attendance', '/attendance/board', '/leave',
        '/hiring/requisitions', '/training', '/training/my', '/people', '/reports', '/reports/people.headcount', '/reports/exports',
        '/reports/schedules', '/organization/branches', "/organization/branches/{$this->branch->ulid}", '/forms', '/request-types',
        '/workflows', '/policies', '/integrations', '/integrations/email', '/notifications', '/notifications/preferences', '/audit',
        '/access/roles', '/settings/profile', '/settings/security', '/settings/preferences', '/people/settings/fields',
        '/hiring/candidates', '/hiring/interviews',
    ];

    foreach ($paths as $path) {
        visit($path)->on()->macbook14()->inDarkMode()->wait(1.2)
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues();
    }

    visit('/reports/builder')->on()->macbook14()->inDarkMode()->wait(1.2)->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('passes axe in dark mode on the public screens', function (string $locale): void {
    visit("/login?lang={$locale}")->on()->mobile()->inDarkMode()->wait(1)->assertNoAccessibilityIssues();
    visit("/careers?lang={$locale}")->on()->mobile()->inDarkMode()->wait(1)->assertNoAccessibilityIssues();
})->with(['en', 'ar']);
