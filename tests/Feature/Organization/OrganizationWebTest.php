<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;

beforeEach(fn () => seedAccess());

function hrAdmin(): User
{
    $user = userWithRoles('hr_admin');
    $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

it('lets HR create, edit and archive a branch', function (): void {
    $payload = [
        'code' => 'nyc', 'name' => ['en' => 'New York'], 'countryCode' => 'US', 'timezone' => 'America/New_York',
        'defaultCurrency' => 'USD', 'phoneRegion' => 'US', 'weekendDays' => [6, 7], 'workStartsAt' => '09:00', 'workEndsAt' => '17:00',
    ];

    $this->actingAs(hrAdmin())->post(route('organization.branches.store'), $payload)->assertRedirect();
    $branch = Branch::query()->sole();

    $this->put(route('organization.branches.update', $branch), [...$payload, 'name' => ['en' => 'NYC', 'ar' => 'نيويورك']])->assertRedirect();
    expect($branch->fresh()?->getTranslation('name', 'ar'))->toBe('نيويورك');

    $this->post(route('organization.branches.store'), $payload)->assertSessionHasErrors(['code', 'error']);

    $this->delete(route('organization.branches.destroy', $branch))->assertRedirect(route('organization.branches.index'));
    expect($branch->fresh()?->trashed())->toBeTrue();
});

it('creates and moves departments, flashing rule violations back to the form', function (): void {
    $this->actingAs(hrAdmin());
    $this->post(route('organization.departments.store'), ['code' => 'ops', 'name' => ['en' => 'Operations']])->assertRedirect();
    $ops = Department::query()->sole();
    $this->post(route('organization.departments.store'), ['code' => 'field', 'name' => ['en' => 'Field'], 'parentId' => $ops->ulid]);
    $field = Department::query()->where('code', 'FIELD')->sole();

    $this->patch(route('organization.departments.move', $ops), ['parentId' => $field->ulid])->assertSessionHasErrors(['parentId', 'error']);
    $this->patch(route('organization.departments.move', $field), ['parentId' => null])->assertSessionHasNoErrors();

    expect($field->fresh()?->path)->toBe((string) $field->id);

    $this->delete(route('organization.departments.destroy', $field))->assertSessionHasNoErrors();
});

it('manages positions and catalog entries', function (): void {
    $this->actingAs(hrAdmin());
    $grade = Grade::factory()->create();

    $this->post(route('organization.positions.store'), ['code' => 'cashier', 'title' => ['en' => 'Cashier'], 'gradeId' => $grade->ulid])->assertRedirect();
    expect(Position::query()->sole()->grade_id)->toBe($grade->id);

    $this->post(route('organization.catalogs.store', 'employment-types'), ['code' => 'ft', 'name' => ['en' => 'Full-time', 'ar' => 'دوام كامل']])->assertRedirect();
    $type = EmploymentType::query()->sole();

    $this->put(route('organization.catalogs.update', ['employment-types', $type->ulid]), ['code' => 'ft', 'name' => ['en' => 'Full time']])->assertRedirect();
    $this->delete(route('organization.catalogs.destroy', ['grades', $type->ulid]))->assertNotFound();
    $this->delete(route('organization.catalogs.destroy', ['employment-types', $type->ulid]))->assertRedirect();
    $this->get('/organization/not-a-catalog')->assertNotFound();

    expect($type->fresh()?->trashed())->toBeTrue();
});

it('keeps organization changes to people with the matching permission', function (): void {
    $branch = Branch::factory()->create();
    $this->actingAs(userWithRoles('employee'));

    $this->get(route('organization.departments.index'))->assertForbidden();
    $this->delete(route('organization.branches.destroy', $branch))->assertForbidden();
    $this->post(route('organization.catalogs.store', 'grades'), ['code' => 'g1', 'name' => ['en' => 'G1']])->assertForbidden();

    expect($branch->fresh()?->trashed())->toBeFalse();
});
