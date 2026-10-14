<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Actions\SaveCustomFieldDefinition;
use App\Domain\People\Actions\SaveImportMapping;
use App\Domain\People\Actions\StartEmployeeImport;
use App\Domain\People\Actions\StartImportCommit;
use App\Domain\People\Data\CustomFieldDefinitionInputData;
use App\Domain\People\Enums\ImportRowStatus;
use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\Import;
use App\Domain\People\Support\Imports\EmployeeImportFields;
use App\Support\Actors\Actor;
use App\Support\Spreadsheets\SpreadsheetReader;
use App\Support\Spreadsheets\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedAccess();
    Storage::fake('local');
    $this->travelTo('2026-10-14 09:00:00');
    $this->hr = userWithRoles('hr_admin');
    $this->ruh = Branch::factory()->riyadh()->create(['code' => 'RUH', 'name' => ['en' => 'Riyadh', 'ar' => 'الرياض']]);
    $this->ops = Department::factory()->create(['code' => 'OPS', 'name' => ['en' => 'Operations', 'ar' => 'العمليات']]);
});

/**
 * @param  list<list<string|int|null>>  $rows
 */
function importFile(array $header, array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    SpreadsheetWriter::write($path, $header, $rows);

    return new UploadedFile($path, 'people.xlsx', null, null, true);
}

function runImport(object $test, UploadedFile $file): Import
{
    $import = resolve(StartEmployeeImport::class)->handle(Actor::user($test->hr), $file);
    resolve(SaveImportMapping::class)->handle($import, $import->mapping);

    return $import->fresh() ?? $import;
}

it('suggests a mapping from English and Arabic headers', function (): void {
    $import = resolve(StartEmployeeImport::class)->handle(Actor::user($this->hr), importFile(
        ['الرقم الوظيفي', 'Full Name', 'الاسم', 'E-mail', 'تاريخ التعيين', 'Branch', 'Unrelated'],
        [],
    ));

    expect($import->mapping)->toBe(['0' => 'employee_number', '1' => 'name_en', '2' => 'name_ar', '3' => 'work_email', '4' => 'hire_date', '5' => 'branch']);
});

it('dry-runs, reports bad rows, then creates and updates with managers first', function (): void {
    $existing = Employee::factory()->placedIn($this->ruh)->create(['employee_number' => 'EMP-000900', 'work_email' => 'old@mawred.test', 'name_en' => 'Old Name']);

    $import = runImport($this, importFile(
        ['Employee number', 'Name', 'Arabic name', 'Email', 'Phone', 'Hire date', 'Branch', 'Department', 'Manager'],
        [
            ['EMP-000901', 'Reem Analyst', 'ريم', 'reem@mawred.test', '0501234567', '01/03/2025', 'RUH', 'العمليات', 'boss@mawred.test'],
            [null, 'Big Boss', null, 'boss@mawred.test', null, '2024-01-01', 'الرياض', 'OPS', null],
            ['EMP-000900', 'New Name', null, null, null, null, null, null, null],
            [null, 'No Branch', null, 'nobranch@mawred.test', null, '2025-01-01', 'Atlantis', null, null],
            [null, 'Dup Email', null, 'reem@mawred.test', null, '2025-01-01', 'RUH', null, null],
            [null, 'Loose', null, 'loose@mawred.test', null, '31/02/2025', 'RUH', null, 'ghost@mawred.test'],
            [null, 'Child Of Broken', null, 'child@mawred.test', null, '2025-01-01', 'RUH', null, 'nobranch@mawred.test'],
        ],
    ));

    expect($import)
        ->status->toBe(ImportStatus::Validated)
        ->total_rows->toBe(7)
        ->valid_rows->toBe(3)
        ->invalid_rows->toBe(4)
        ->to_create->toBe(2)
        ->to_update->toBe(1)
        ->and(Employee::query()->count())->toBe(1);

    $errors = $import->rows()->where('status', ImportRowStatus::Invalid)->orderBy('row_number')->pluck('errors', 'row_number')->all();
    expect(array_keys($errors[5]))->toBe(['branch'])
        ->and(array_keys($errors[6]))->toBe(['work_email'])
        ->and($errors[7])->toHaveKeys(['hire_date'])
        ->and(array_keys($errors[8]))->toBe(['manager']);

    $report = $import->getFirstMedia('error_report');
    $reportRows = iterator_to_array(SpreadsheetReader::rows($report?->getPath() ?? '', 'xlsx'), false);
    expect($reportRows)->toHaveCount(5)
        ->and($reportRows[0])->toContain('Errors')
        ->and(end($reportRows[1]))->toContain('branch:');

    resolve(StartImportCommit::class)->handle($import);
    $import->refresh();

    expect($import)->status->toBe(ImportStatus::Completed)->created_rows->toBe(2)->updated_rows->toBe(1)->failed_rows->toBe(0);

    $boss = Employee::query()->where('work_email', 'boss@mawred.test')->sole();
    $reem = Employee::query()->where('employee_number', 'EMP-000901')->sole();

    expect($reem)
        ->current_manager_id->toBe($boss->id)
        ->current_department_id->toBe($this->ops->id)
        ->work_phone_e164->toBe('+966501234567')
        ->hire_date->toDateString()->toBe('2025-03-01')
        ->and($boss->employee_number)->toMatch('/^EMP-\d{6}$/')
        ->and($existing->fresh())->name_en->toBe('New Name')->work_email->toBe('old@mawred.test');
});

it('refuses updates outside the importer’s scope and sensitive columns without field access', function (): void {
    $officer = userWithRoles('hr_officer'); // branch scope; placed in Riyadh
    Employee::factory()->placedIn($this->ruh)->create(['user_id' => $officer->id]);
    $jeddah = Branch::factory()->riyadh()->create(['code' => 'JED']);
    Employee::factory()->placedIn($jeddah)->create(['employee_number' => 'EMP-000500']);
    resolve(SaveCustomFieldDefinition::class)->handle(CustomFieldDefinitionInputData::from([
        'key' => 'medical_note', 'label' => ['en' => 'Medical note'], 'type' => 'text', 'isSensitive' => true,
    ]));
    $this->hr = $officer;

    $import = runImport($this, importFile(
        ['Employee number', 'Name', 'Hire date', 'Branch', 'Medical note'],
        [
            ['EMP-000500', 'Changed', null, null, null],
            [null, 'New Hire', '2025-01-01', 'RUH', 'Asthma'],
        ],
    ));

    $errors = $import->rows()->orderBy('row_number')->pluck('errors', 'row_number')->all();

    expect($import->mapping)->toContain('custom.medical_note')
        ->and($errors[2])->toHaveKey('employee_number')
        ->and($errors[3])->toHaveKey('custom.medical_note');
});

it('detects reporting loops inside the file', function (): void {
    $import = runImport($this, importFile(
        ['Name', 'Email', 'Hire date', 'Branch', 'Manager'],
        [
            ['A', 'a@mawred.test', '2025-01-01', 'RUH', 'b@mawred.test'],
            ['B', 'b@mawred.test', '2025-01-01', 'RUH', 'a@mawred.test'],
            ['C', 'c@mawred.test', '2025-01-01', 'RUH', 'a@mawred.test'],
        ],
    ));

    expect($import->valid_rows)->toBe(0)->and($import->invalid_rows)->toBe(3);
});

it('maps its own template back in both languages', function (string $locale): void {
    $titles = EmployeeImportFields::titles($locale);

    expect(array_values(EmployeeImportFields::suggest($titles)))
        ->toBe(array_keys(EmployeeImportFields::BUILT_IN));
})->with(['en', 'ar']);

it('keeps imports private to whoever started them', function (): void {
    $this->hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $import = resolve(StartEmployeeImport::class)->handle(Actor::user($this->hr), importFile(['Name', 'Email', 'Hire date', 'Branch'], [['Sam', 'sam@mawred.test', '2025-01-01', 'RUH']]));

    $this->actingAs($this->hr)->put(route('people.imports.mapping', $import), ['mapping' => ['0' => 'name_en', '1' => 'work_email', '2' => 'hire_date', '3' => 'branch']])->assertRedirect();
    expect($import->fresh()?->status)->toBe(ImportStatus::Validated);

    $this->post(route('people.imports.commit', $import))->assertRedirect();
    expect(Employee::query()->where('work_email', 'sam@mawred.test')->exists())->toBeTrue();

    $this->get(route('people.imports.template'))->assertOk()->assertDownload('mawred-employee-import.xlsx');

    $other = userWithRoles('hr_admin');
    $other->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($other)->post(route('people.imports.commit', $import))->assertNotFound();
    $this->actingAs(userWithRoles('employee'))->get(route('people.imports.template'))->assertForbidden();
});
