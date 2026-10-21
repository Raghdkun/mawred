<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Http\Controllers\Public\CspReportController;
use Illuminate\Support\Facades\Cache;

/*
| Phase 9a: the Content-Security-Policy holds across the main screens: nothing the app loads or
| runs (Vite chunks, inline theme script, charts, the geofence map, live updates) is blocked.
| Browsers report violations to /hooks/csp-report, which keeps them in the cache.
*/
it('loads the main screens without a single policy violation', function (): void {
    seedAccess();
    Cache::forget(CspReportController::CACHE_KEY);
    $branch = Branch::factory()->riyadh()->withGeofence()->create();
    $hr = userWithRoles('hr_admin');
    $hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Employee::factory()->count(3)->placedIn($branch)->create();

    visit('/login')->on()->macbook14()->wait(1)->assertNoJavaScriptErrors();

    $this->actingAs($hr);

    foreach (['/dashboard', '/people', "/organization/branches/{$branch->ulid}", '/reports/people.headcount', '/reports/builder', '/calendar', '/training', '/hiring/requisitions', '/integrations', '/audit'] as $path) {
        visit($path)->on()->macbook14()->wait(1.5)->assertNoJavaScriptErrors();
    }

    visit('/careers')->on()->mobile()->wait(1)->assertNoJavaScriptErrors();

    expect(Cache::get(CspReportController::CACHE_KEY, []))->toBe([]);
});
