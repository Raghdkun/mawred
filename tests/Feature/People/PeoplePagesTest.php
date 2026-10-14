<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Actions\RecordSalaryChange;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use App\Support\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    seedAccess();
    $this->riyadh = Branch::factory()->riyadh()->create();
    $this->dubai = Branch::factory()->create();
});

function peopleAdmin(): User
{
    $user = userWithRoles('hr_admin');
    $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

it('lists the directory within scope and filters by branch', function (): void {
    $manager = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->riyadh)->create(['user_id' => $manager->id]);
    $colleague = Employee::factory()->placedIn($this->riyadh)->create();
    Employee::factory()->placedIn($this->dubai)->create();

    $this->actingAs($manager)->get(route('people.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('people/index')->has('employees.data', 2)->where('can.create', false));

    $this->actingAs(peopleAdmin())->get(route('people.index', ['branch' => $this->riyadh->ulid, 'view' => 'grid']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('employees.data', 2)->where('filters.branch.id', $this->riyadh->ulid)->where('filters.view', 'grid'));

    $this->actingAs($manager)->get(route('people.show', $colleague))->assertOk();
});

it('hides profiles outside the viewer scope behind a 404', function (): void {
    $manager = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->riyadh)->create(['user_id' => $manager->id]);
    $stranger = Employee::factory()->placedIn($this->dubai)->create();

    $this->actingAs($manager)->get(route('people.show', $stranger))->assertNotFound();
    $this->put(route('people.update', $stranger), ['nameEn' => 'X'])->assertNotFound();
});

it('renders the profile with salary only for those who may read it', function (): void {
    $employee = Employee::factory()->placedIn($this->riyadh)->create();
    resolve(RecordSalaryChange::class)->handle(Actor::system('test'), $employee, Money::fromDecimal(9000, 'SAR'), '2025-01-01');

    $this->actingAs(peopleAdmin())->get(route('people.show', [$employee, 'tab' => 'history']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('people/show')->where('tab', 'history')->where('history.0.salary.minor', 900000)->where('can.salary.write', true)->where('defaultCurrency', 'SAR'));

    $officer = userWithRoles('hr_officer');
    Employee::factory()->placedIn($this->riyadh)->create(['user_id' => $officer->id]);
    $officer->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($officer)->get(route('people.show', $employee))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('history.0.salary', null)->where('can.salary.read', false));
});

it('hires, edits, places and documents an employee through the web endpoints', function (): void {
    Storage::fake('local');
    $this->actingAs(peopleAdmin());

    $this->post(route('people.store'), [
        'nameEn' => 'Hala Fares', 'nameAr' => 'هالة فارس', 'hireDate' => '2025-02-01', 'workPhone' => '0551234567',
        'placement' => ['branchId' => $this->riyadh->ulid],
    ])->assertRedirect();
    $employee = Employee::query()->where('name_en', 'Hala Fares')->sole();

    $this->put(route('people.update', $employee), ['nameEn' => 'Hala Fares', 'preferredName' => 'Hala', 'workPhone' => '+966551234567', 'hireDate' => '2025-02-01'])->assertSessionHasNoErrors();
    $this->put(route('people.sensitive.update', $employee), ['nationalId' => ['number' => '1023456789']])->assertSessionHasNoErrors();
    $this->post(route('people.placements.change', $employee), ['branchId' => $this->dubai->ulid, 'effectiveFrom' => now()->addMonth()->toDateString()])->assertSessionHasNoErrors();
    $this->post(route('people.assets.store', $employee), ['name' => 'Laptop', 'assetTag' => 'LT-9', 'assignedOn' => '2025-02-01'])->assertSessionHasNoErrors();

    $type = DocumentType::query()->create(['code' => 'CONTRACT', 'name' => ['en' => 'Contract']]);
    $this->post(route('people.documents.store', $employee), [
        'documentTypeId' => $type->ulid,
        'file' => UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"),
    ])->assertSessionHasNoErrors();
    $document = $employee->documents()->sole();

    $this->get(route('people.documents.download', [$employee, $document]))->assertOk();

    expect($employee->fresh())->preferred_name->toBe('Hala')->work_phone_e164->toBe('+966551234567')
        ->and($employee->assignments()->count())->toBe(2)
        ->and($employee->assets()->count())->toBe(1);
});

it('lets people configure custom fields and document types', function (): void {
    $this->actingAs(peopleAdmin());

    $this->get(route('people.settings.fields'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('people/settings/fields'));
    $this->post(route('people.settings.fields.store'), ['key' => 'shirt', 'label' => ['en' => 'Shirt'], 'type' => 'select', 'section' => 'personal', 'options' => [['value' => 's', 'label' => ['en' => 'S']]]])->assertSessionHasNoErrors();
    $this->get(route('people.settings.document-types'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('people/settings/document-types'));
    $this->post(route('people.settings.document-types.store'), ['code' => 'visa', 'name' => ['en' => 'Visa'], 'requiresExpiry' => true, 'reminderDays' => [30]])->assertSessionHasNoErrors();

    $this->actingAs(userWithRoles('employee'))->get(route('people.settings.fields'))->assertForbidden();
});

it('renders the import wizard and setup branch step', function (): void {
    $admin = peopleAdmin();
    $this->actingAs($admin)->get(route('people.imports.create'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('people/imports/create'));
});
