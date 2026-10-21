<?php

declare(strict_types=1);

use App\Domain\People\Actions\SaveEmployeeDocument;
use App\Domain\People\Actions\StartEmployeeImport;
use App\Domain\People\Data\EmployeeDocumentInputData;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\People\Models\Import;
use App\Domain\Setup\Actions\SaveCompanyProfile;
use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Files\FileScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Phase 9a: every path that keeps a file someone sent runs it past the virus scanner first
| (ADR-0008). Form uploads were already covered (FormLifecycleTest).
*/
beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    seedAccess();
    $this->actor = Actor::user(userWithRoles('hr_admin'));
    $this->app->instance(FileScanner::class, new class implements FileScanner
    {
        public function isClean(string $path): bool
        {
            return false;
        }
    });
});

it('refuses an employee document the scanner flags', function (): void {
    $type = DocumentType::query()->create(['code' => 'ID', 'name' => ['en' => 'ID', 'ar' => 'الهوية']]);
    $employee = Employee::factory()->create();

    expect(fn () => resolve(SaveEmployeeDocument::class)->handle($this->actor, $employee, EmployeeDocumentInputData::from(['documentTypeId' => $type->ulid]), UploadedFile::fake()->create('id.pdf', 10, 'application/pdf')))
        ->toThrow(DomainRuleViolation::class, 'virus');
    expect(EmployeeDocument::query()->count())->toBe(0);
});

it('refuses an import file the scanner flags', function (): void {
    expect(fn () => resolve(StartEmployeeImport::class)->handle($this->actor, UploadedFile::fake()->createWithContent('people.csv', "employee_number,name_en\nE1,Ann\n")))
        ->toThrow(DomainRuleViolation::class, 'virus');
    expect(Import::query()->count())->toBe(0);
});

it('refuses a company logo the scanner flags', function (): void {
    expect(fn () => resolve(SaveCompanyProfile::class)->handle($this->actor, ['en' => 'Acme', 'ar' => 'أكمي'], null, null, UploadedFile::fake()->image('logo.png')))
        ->toThrow(DomainRuleViolation::class, 'virus');
    expect(resolve(Settings::class)->get('company.logo_path'))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
