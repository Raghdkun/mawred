<?php

declare(strict_types=1);

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Notifications\Notifications\InAppNotification;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Actions\AssignAsset;
use App\Domain\People\Actions\HireEmployee;
use App\Domain\People\Actions\RecordSalaryChange;
use App\Domain\People\Actions\ReturnAsset;
use App\Domain\People\Actions\SaveAllowance;
use App\Domain\People\Actions\SaveCustomFieldDefinition;
use App\Domain\People\Actions\SaveDocumentType;
use App\Domain\People\Actions\SaveEmployeeDocument;
use App\Domain\People\Actions\SendDocumentExpiryReminders;
use App\Domain\People\Actions\SetCustomFieldValues;
use App\Domain\People\Actions\UpdateSensitiveDetails;
use App\Domain\People\Data\AllowanceInputData;
use App\Domain\People\Data\CustomFieldDefinitionInputData;
use App\Domain\People\Data\DocumentTypeInputData;
use App\Domain\People\Data\EmployeeDocumentInputData;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Data\SensitiveDetailsInputData;
use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAllowance;
use App\Domain\People\Models\EmployeeCustomValue;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => seedAccess());

function hrActor(): Actor
{
    return Actor::user(userWithRoles('hr_admin'));
}

it('defines custom fields and validates values by type', function (): void {
    $save = resolve(SaveCustomFieldDefinition::class);
    $save->handle(CustomFieldDefinitionInputData::from(['key' => 'tshirt', 'label' => ['en' => 'T-shirt size'], 'type' => 'select', 'options' => [['value' => 's', 'label' => ['en' => 'S']], ['value' => 'm', 'label' => ['en' => 'M']]]]));
    $save->handle(CustomFieldDefinitionInputData::from(['key' => 'start_bonus', 'label' => ['en' => 'Signing bonus'], 'type' => 'number', 'isSensitive' => true]));

    $employee = Employee::factory()->placedIn(Branch::factory()->riyadh()->create())->create();

    expect(fn () => resolve(SetCustomFieldValues::class)->handle(hrActor(), $employee, ['tshirt' => 'xxl', 'nope' => 1]))
        ->toThrow(fn (DomainRuleViolation $e) => expect(array_keys($e->details))->toBe(['custom.tshirt', 'custom.nope']));

    resolve(SetCustomFieldValues::class)->handle(hrActor(), $employee, ['tshirt' => 'm', 'start_bonus' => '1500']);

    $bonus = EmployeeCustomValue::query()->whereHas('definition', fn (Builder $q) => $q->where('key', 'start_bonus'))->sole();
    expect($bonus->resolved())->toBe(1500)
        ->and($bonus->getRawOriginal('value'))->toBeNull()
        ->and(DB::table('employee_custom_values')->where('id', $bonus->id)->value('value_encrypted'))->not->toContain('1500');

    // HR admin got write on the new sensitive key (people.configure); an HR officer did not.
    expect(resolve(ScopeResolver::class)->fieldAccess(userWithRoles('hr_admin'), 'employee.custom.start_bonus'))->toBe(FieldAccess::Write);
    expect(fn () => resolve(SetCustomFieldValues::class)->handle(Actor::user(userWithRoles('hr_officer')), $employee, ['start_bonus' => 1]))
        ->toThrow(DomainRuleViolation::class, 'permission');

    expect(fn () => $save->handle(CustomFieldDefinitionInputData::from(['key' => 'start_bonus', 'label' => ['en' => 'x'], 'type' => 'text', 'isSensitive' => true]), $bonus->definition))
        ->toThrow(DomainRuleViolation::class);
});

it('encrypts sensitive details, indexes them blindly and enforces field permissions', function (): void {
    $employee = Employee::factory()->placedIn(Branch::factory()->riyadh()->create())->create();

    resolve(UpdateSensitiveDetails::class)->handle(hrActor(), $employee, SensitiveDetailsInputData::from([
        'nationalId' => ['number' => '1012 345 678'],
        'bank' => ['iban' => 'sa03 8000 0000 6080 1016 7519', 'bankName' => 'Al Rajhi'],
        'personalContact' => ['phone' => '0501234567', 'emergencyName' => 'Mona'],
    ]));

    $raw = DB::table('employees')->where('id', $employee->id)->first();
    expect($raw->national_id)->not->toContain('1012')
        ->and($raw->national_id_bidx)->toBe(Employee::lookupHash('national_id', '1012345678'))
        ->and(Employee::query()->where('national_id_bidx', Employee::lookupHash('national_id', '1012-345-678'))->sole()->is($employee))->toBeTrue()
        ->and($employee->fresh()?->bank_details)->toBe(['bank_name' => 'Al Rajhi', 'iban' => 'SA0380000000608010167519'])
        ->and($employee->fresh()?->personal_contact['phone'] ?? null)->toBe('+966501234567');

    $officer = Actor::user(userWithRoles('hr_officer'));
    expect(fn () => resolve(UpdateSensitiveDetails::class)->handle($officer, $employee, SensitiveDetailsInputData::from(['bank' => ['iban' => null]])))
        ->toThrow(DomainRuleViolation::class);

    $activity = DB::table('activity_log')->where('subject_id', $employee->id)->where('event', 'updated')->latest('id')->value('properties');
    expect($activity)->not->toContain('1012')->not->toContain('SA03');
});

it('keeps salary as encrypted, permissioned history', function (): void {
    $employee = Employee::factory()->placedIn(Branch::factory()->create())->create();

    $entry = resolve(RecordSalaryChange::class)->handle(hrActor(), $employee, Money::fromDecimal('12500.50', 'SAR'), '2026-01-01', 'Annual review');

    expect($entry->fresh()?->salary)->toEqual(new Money(1250050, 'SAR'))
        ->and(DB::table('employment_history')->where('id', $entry->id)->value('salary_minor'))->not->toContain('1250050');

    expect(fn () => resolve(RecordSalaryChange::class)->handle(Actor::user(userWithRoles('hr_officer')), $employee, Money::fromDecimal(1, 'SAR'), '2026-02-01'))
        ->toThrow(DomainRuleViolation::class);
});

it('keeps allowances on the record, encrypted, in the history and behind the salary permission', function (): void {
    $employee = Employee::factory()->placedIn(Branch::factory()->riyadh()->create())->create();
    $hr = userWithRoles('hr_admin');
    $hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($hr)->post(route('people.allowances.store', $employee), [
        'type' => 'housing', 'amount' => '2500', 'currency' => 'SAR', 'effectiveFrom' => '2026-11-01',
    ])->assertRedirect();
    $allowance = EmployeeAllowance::query()->sole();

    expect($allowance->amount)->toEqual(new Money(250000, 'SAR'))
        ->and(DB::table('employee_allowances')->where('id', $allowance->id)->value('amount_minor'))->not->toContain('250000');

    // Ending it is a change on the timeline, with the amount kept encrypted there too.
    $this->put(route('people.allowances.update', [$employee, $allowance]), [
        'type' => 'housing', 'amount' => '2500', 'currency' => 'SAR', 'effectiveFrom' => '2026-11-01', 'effectiveTo' => '2027-03-31',
    ])->assertRedirect();
    expect($employee->history()->where('event', HistoryEvent::AllowanceChanged->value)->pluck('changes')->pluck('action')->all())->toEqualCanonicalizing(['added', 'ended']);
    $this->get(route('people.show', $employee))->assertInertia(fn ($page) => $page->where('allowances.0.amount.minor', 250000)->where('allowances.0.effectiveTo', '2027-03-31'));

    // Without the salary field permission: no changes, and no amounts on the profile.
    expect(fn () => resolve(SaveAllowance::class)->handle(Actor::user(userWithRoles('hr_officer')), $employee, AllowanceInputData::from([
        'type' => 'transport', 'amount' => '1', 'currency' => 'SAR', 'effectiveFrom' => '2026-11-01',
    ])))->toThrow(DomainRuleViolation::class);
    $auditor = userWithRoles('auditor');
    $auditor->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($auditor)->get(route('people.show', $employee))->assertInertia(fn ($page) => $page->where('allowances', null));
    expect(EmployeeAllowance::query()->count())->toBe(1);
});

it('stores documents privately and reminds each threshold once', function (): void {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2026-10-01 08:00:00');

    $type = resolve(SaveDocumentType::class)->handle(new DocumentTypeInputData('iqama', ['en' => 'Residency permit', 'ar' => 'الإقامة'], true, [30, 7]));
    $branch = Branch::factory()->riyadh()->create();
    $managerUser = User::factory()->create();
    $manager = Employee::factory()->placedIn($branch)->create(['user_id' => $managerUser->id]);
    $employeeUser = User::factory()->create(['locale' => 'ar']);
    $employee = Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $employeeUser->id]);
    $hr = userWithRoles('hr_admin');
    $officerElsewhere = userWithRoles('hr_officer'); // branch scope, no placement → not a recipient

    expect(fn () => resolve(SaveEmployeeDocument::class)->handle(hrActor(), $employee, new EmployeeDocumentInputData($type->ulid), UploadedFile::fake()->createWithContent('iqama.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n")))
        ->toThrow(DomainRuleViolation::class, 'expiry');

    $document = resolve(SaveEmployeeDocument::class)->handle(hrActor(), $employee, new EmployeeDocumentInputData($type->ulid, '2412345678', expiresOn: '2026-10-25'), UploadedFile::fake()->createWithContent('iqama.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"));

    expect($document->getFirstMedia('file')?->disk)->toBe('local');

    resolve(SendDocumentExpiryReminders::class)->handle();
    resolve(SendDocumentExpiryReminders::class)->handle();

    Notification::assertSentToTimes($employeeUser, InAppNotification::class, 1);
    Notification::assertSentToTimes($managerUser, InAppNotification::class, 1);
    Notification::assertSentToTimes($hr, InAppNotification::class, 1);
    Notification::assertNotSentTo($officerElsewhere, InAppNotification::class);
    Notification::assertSentTo($employeeUser, fn (InAppNotification $n): bool => $n->event === 'documents.expiring' && str_contains($n->title, 'الإقامة') && str_contains($n->title, '24'));

    $this->travelTo('2026-10-19 08:00:00');
    resolve(SendDocumentExpiryReminders::class)->handle();
    Notification::assertSentToTimes($employeeUser, InAppNotification::class, 2);
    expect($document->fresh()?->reminders_sent)->toBe([30, 7]);
});

it('tracks assets and refuses to hand out one that is still out', function (): void {
    $branch = Branch::factory()->create();
    [$a, $b] = Employee::factory()->count(2)->placedIn($branch)->create();

    $laptop = resolve(AssignAsset::class)->handle($a, 'MacBook Air', 'lt-001', '2026-01-10');

    expect(fn () => resolve(AssignAsset::class)->handle($b, 'MacBook Air', 'LT-001', '2026-02-01'))->toThrow(DomainRuleViolation::class);

    resolve(ReturnAsset::class)->handle($laptop, '2026-01-31');
    expect(resolve(AssignAsset::class)->handle($b, 'MacBook Air', 'LT-001', '2026-02-01')->employee_id)->toBe($b->id);
});

it('writes the employment timeline', function (): void {
    $employee = resolve(HireEmployee::class)->handle(hrActor(), HireEmployeeData::from([
        'nameEn' => 'Omar Saleh', 'hireDate' => '2025-01-01', 'placement' => ['branchId' => Branch::factory()->create()->ulid],
    ]));

    expect($employee->history()->pluck('event')->all())->toBe([HistoryEvent::Hired]);
});
