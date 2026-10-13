<?php

declare(strict_types=1);

use App\Domain\Organization\Actions\SaveBranch;
use App\Domain\Organization\Actions\SaveCatalogEntry;
use App\Domain\Organization\Actions\SavePosition;
use App\Domain\Organization\Data\BranchInputData;
use App\Domain\Organization\Data\CatalogEntryInputData;
use App\Domain\Organization\Data\PositionInputData;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Grade;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function branchInput(array $overrides = []): BranchInputData
{
    return BranchInputData::from([
        'code' => 'ruh-01',
        'name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي — الرياض'],
        'countryCode' => 'sa',
        'timezone' => 'Asia/Riyadh',
        'defaultCurrency' => 'sar',
        'phoneRegion' => 'sa',
        'weekendDays' => [6, 5, 5],
        'workStartsAt' => '08:00',
        'workEndsAt' => '16:30',
        'address' => ['city' => 'Riyadh', 'line1' => ''],
        'geofence' => ['lat' => 24.7136123456, 'lng' => 46.6753, 'radiusM' => 150],
        ...$overrides,
    ]);
}

it('saves a branch with normalised values and search text', function (): void {
    $costCenter = CostCenter::factory()->create();

    $branch = resolve(SaveBranch::class)->handle(branchInput(['costCenterId' => $costCenter->ulid]));

    expect($branch->fresh())
        ->code->toBe('RUH-01')
        ->country_code->toBe('SA')
        ->default_currency->toBe('SAR')
        ->weekend_days->toBe([5, 6])
        ->address->toBe(['city' => 'Riyadh'])
        ->cost_center_id->toBe($costCenter->id)
        ->hasGeofence()->toBeTrue()
        ->and((float) $branch->fresh()?->geofence_lat)->toBe(24.713612)
        ->and($branch->fresh()?->search_text)->toContain('ruh-01')->toContain('المقر الرييسي');

    expect(Activity::query()->where('subject_type', $branch->getMorphClass())->where('event', 'created')->exists())->toBeTrue();
});

it('edits a branch and clears its geofence', function (): void {
    $branch = Branch::factory()->withGeofence()->create(['code' => 'NYC']);

    resolve(SaveBranch::class)->handle(branchInput(['code' => 'nyc', 'geofence' => null]), $branch);

    expect($branch->fresh())->code->toBe('NYC')->hasGeofence()->toBeFalse()->timezone->toBe('Asia/Riyadh');
});

it('rejects a duplicate branch code', function (): void {
    Branch::factory()->create(['code' => 'RUH-01']);

    expect(fn () => resolve(SaveBranch::class)->handle(branchInput()))
        ->toThrow(function (DomainRuleViolation $e): void {
            expect($e->errorCode)->toBe('organization.code_taken')->and($e->details)->toHaveKey('code');
        });
});

it('validates branch input', function (): void {
    expect(fn (): BranchInputData => BranchInputData::validateAndCreate([
        'code' => 'bad code',
        'name' => ['ar' => 'فرع'],
        'countryCode' => 'SAU',
        'timezone' => 'Mars/Olympus',
        'defaultCurrency' => 'SAR',
        'phoneRegion' => 'SA',
        'weekendDays' => [8],
        'workStartsAt' => '09:00',
        'workEndsAt' => '09:00',
        'geofence' => ['lat' => 120, 'lng' => 46, 'radiusM' => 5],
    ]))->toThrow(ValidationException::class);
});

it('saves positions linked to a department and grade', function (): void {
    $department = Department::factory()->create();
    $grade = Grade::factory()->create();

    $position = resolve(SavePosition::class)->handle(new PositionInputData(
        code: 'mgr', title: ['en' => 'Store Manager', 'ar' => 'مدير المتجر'], departmentId: $department->ulid, gradeId: $grade->ulid,
    ));

    expect($position->fresh())->department_id->toBe($department->id)->grade_id->toBe($grade->id)
        ->and($position->getTranslation('title', 'ar'))->toBe('مدير المتجر');
});

it('saves catalog entries and only ranks grades', function (): void {
    $grade = resolve(SaveCatalogEntry::class)->handle(CatalogKind::Grade, new CatalogEntryInputData('g5', ['en' => 'Grade 5', 'ar' => ''], 5));
    $type = resolve(SaveCatalogEntry::class)->handle(CatalogKind::EmploymentType, new CatalogEntryInputData('ft', ['en' => 'Full-time'], 9));

    expect($grade)->toBeInstanceOf(Grade::class)->rank->toBe(5)
        ->and($grade->getTranslations('name'))->toBe(['en' => 'Grade 5'])
        ->and($grade->getTranslation('name', 'ar'))->toBe('Grade 5')
        ->and($type->getAttribute('rank'))->toBeNull()
        ->and($type->code)->toBe('FT');

    expect(fn () => resolve(SaveCatalogEntry::class)->handle(CatalogKind::CostCenter, new CatalogEntryInputData('x', ['en' => 'X']), $grade))
        ->toThrow(InvalidArgumentException::class);
});
