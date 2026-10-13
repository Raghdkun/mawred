<?php

declare(strict_types=1);

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Actions\SaveCustomFieldDefinition;
use App\Domain\People\Actions\SetCustomFieldValues;
use App\Domain\People\Data\CustomFieldDefinitionInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Support\EmployeeProfiles;
use App\Support\Actors\Actor;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    seedAccess();
    $this->employee = Employee::factory()->placedIn(Branch::factory()->create())->create([
        'national_id' => '1012345678',
        'bank_details' => ['iban' => 'SA0380000000608010167519'],
        'date_of_birth' => '1990-05-01',
    ]);
    resolve(SaveCustomFieldDefinition::class)->handle(CustomFieldDefinitionInputData::from(['key' => 'shoe_size', 'label' => ['en' => 'Shoe size'], 'type' => 'number']));
    resolve(SaveCustomFieldDefinition::class)->handle(CustomFieldDefinitionInputData::from(['key' => 'medical_note', 'label' => ['en' => 'Medical note'], 'type' => 'text', 'isSensitive' => true]));
    resolve(SetCustomFieldValues::class)->handle(Actor::system('test'), $this->employee, ['shoe_size' => 42, 'medical_note' => 'Asthma']);
});

function sensitiveViews(): int
{
    return DB::table('activity_log')->where('log_name', 'sensitive')->count();
}

it('shows HR admins every sensitive field and audits the read once per request', function (): void {
    $profiles = resolve(EmployeeProfiles::class);
    $hr = userWithRoles('hr_admin');

    $profile = $profiles->for($hr, $this->employee);
    $profiles->for($hr, $this->employee);

    expect($profile->sensitive->nationalId)->toBe('1012345678')
        ->and($profile->sensitive->access['employee.salary'] ?? null)->toBeNull() // salary is history, not shown here
        ->and($profile->sensitive->access['employee.bank'])->toBe('write')
        ->and(collect($profile->customFields)->firstWhere('key', 'medical_note')?->value)->toBe('Asthma')
        ->and(sensitiveViews())->toBe(1);

    $properties = json_decode((string) DB::table('activity_log')->where('log_name', 'sensitive')->value('properties'), true);
    expect($properties['fields'])->toEqualCanonicalizing(['employee.national_id', 'employee.bank', 'employee.date_of_birth', 'employee.custom.medical_note'])
        ->and(json_encode($properties))->not->toContain('1012345678');
});

it('hides what the viewer has no field access to', function (): void {
    $officer = userWithRoles('hr_officer');

    $profile = resolve(EmployeeProfiles::class)->for($officer, $this->employee);

    expect($profile->sensitive->nationalId)->toBe('1012345678') // hr_officer: national_id write
        ->and($profile->sensitive->bank)->toBeNull()
        ->and($profile->sensitive->access)->not->toHaveKey('employee.bank')
        ->and(collect($profile->customFields)->firstWhere('key', 'medical_note')?->access)->toBe('hidden')
        ->and(collect($profile->customFields)->firstWhere('key', 'medical_note')?->value)->toBeNull()
        ->and(collect($profile->customFields)->firstWhere('key', 'shoe_size')?->value)->toBe(42);
});

it('logs nothing when no sensitive value is shown', function (): void {
    resolve(EmployeeProfiles::class)->for(User::factory()->create(), $this->employee);

    expect(sensitiveViews())->toBe(0);
});

it('puts sensitive custom fields in the role field matrix', function (): void {
    $admin = userWithRoles('hr_admin');
    $admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $officer = Role::findByName('hr_officer');

    $this->actingAs($admin)->get(route('access.roles.show', $officer))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('sensitiveFields', fn ($fields): bool => collect($fields)->contains('employee.custom.medical_note') && collect($fields)->doesntContain('employee.custom.shoe_size'))
            ->where('fieldLabels', ['employee.custom.medical_note' => ['en' => 'Medical note']]));

    $this->put(route('access.roles.fields.update', $officer), ['fields' => ['employee.custom.medical_note' => 'read', 'employee.custom.nope' => 'write']])->assertRedirect();

    app()->forgetScopedInstances();
    $profile = resolve(EmployeeProfiles::class)->for(userWithRoles('hr_officer'), $this->employee);

    expect(collect($profile->customFields)->firstWhere('key', 'medical_note')?->access)->toBe('read')
        ->and(DB::table('field_permissions')->where('field_key', 'employee.custom.nope')->exists())->toBeFalse();
});
