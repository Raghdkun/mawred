<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedAccess();
    $this->root = userWithRoles('super_admin');
    $this->root->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->riyadh = Branch::factory()->riyadh()->withGeofence()->create(['code' => 'RUH', 'name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->ops = Department::factory()->create(['code' => 'OPS', 'name' => ['en' => 'Operations', 'ar' => 'العمليات']]);
    Department::factory()->childOf($this->ops)->create(['code' => 'FIELD', 'name' => ['en' => 'Field', 'ar' => 'الميدان']]);
    $this->manager = Employee::factory()->placedIn($this->riyadh, $this->ops)->create(['name_en' => 'Sara Haddad', 'name_ar' => 'سارة حداد', 'employee_number' => 'EMP-000001']);
    Employee::factory()->placedIn($this->riyadh, $this->ops, $this->manager)->create(['name_en' => 'Omar Saleh', 'name_ar' => 'عمر صالح', 'employee_number' => 'EMP-000002']);
});

it('renders the Phase 1 screens accessibly in both languages', function (string $locale, string $path): void {
    $this->root->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->root);

    visit(str_replace('{manager}', $this->manager->ulid, $path))
        ->wait(0.8)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar'])->with([
    'directory' => '/people',
    'directory grid' => '/people?view=grid',
    'org chart' => '/people?view=org',
    'profile' => '/people/{manager}',
    'profile personal' => '/people/{manager}?tab=personal',
    'profile job' => '/people/{manager}?tab=job',
    'branches' => '/organization/branches',
    'departments' => '/organization/departments',
    'positions' => '/organization/positions',
    'catalogs' => '/organization/employment-types',
    'import' => '/people/imports/new',
    'custom fields' => '/people/settings/fields',
]);

it('searches the directory and opens a profile', function (): void {
    $this->actingAs($this->root);

    visit('/people')
        ->fill('[role=search] input', 'omar')
        ->keys('[role=search] input', 'Enter')
        ->wait(0.6)
        ->assertSee('Omar Saleh')
        ->assertDontSee('EMP-000001')
        ->click('Omar Saleh')
        ->wait(0.6)
        ->assertPathBeginsWith('/people/')
        ->assertSee('EMP-000002')
        ->assertNoJavaScriptErrors();
});

it('adds an employee from the directory', function (): void {
    $this->actingAs($this->root);

    visit('/people')
        ->click('Add employee')
        ->wait(0.4)
        ->fill('#add-name-en', 'Lina Odeh')
        ->fill('#add-email', 'lina@mawred.test')
        ->click('[role=dialog] [aria-label="Branch"]')
        ->wait(0.6)
        ->click('[role=option]:has-text("Riyadh HQ")')
        ->click('button[form=add-employee-form]')
        ->wait(1)
        ->assertSee('Lina Odeh')
        ->assertNoJavaScriptErrors();

    expect(Employee::query()->where('work_email', 'lina@mawred.test')->exists())->toBeTrue();
});

it('imports employees from a CSV end to end', function (): void {
    Storage::fake('local');
    $this->actingAs($this->root);
    $csv = tempnam(sys_get_temp_dir(), 'imp').'.csv';
    file_put_contents($csv, "Name,Email,Hire date,Branch,Manager\nNoura Ali,noura@mawred.test,2025-01-05,RUH,EMP-000001\nBad Row,bad-email,2025-01-05,Atlantis,\n");

    visit('/people/imports/new')
        ->attach('#import-file', $csv)
        ->click('Upload and continue')
        ->wait(1)
        ->assertSee('Map columns')
        ->click('Check the file')
        ->wait(2)
        ->assertSee('Rows that need fixing')
        ->click('Import 1 row')
        ->wait(2)
        ->assertSee('Import finished')
        ->assertNoJavaScriptErrors();

    expect(Employee::query()->where('work_email', 'noura@mawred.test')->sole()->current_manager_id)->toBe($this->manager->id);
});
