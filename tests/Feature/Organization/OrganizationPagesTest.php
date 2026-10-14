<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => seedAccess());

function orgAdmin(): User
{
    $user = userWithRoles('hr_admin');
    $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

it('renders every organization screen', function (): void {
    $branch = Branch::factory()->withGeofence()->create();
    $root = Department::factory()->create();
    Department::factory()->childOf($root)->create();
    Position::factory()->create(['department_id' => $root->id]);
    $this->actingAs(orgAdmin());

    $this->get(route('organization.branches.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('organization/branches/index')->has('branches.data', 1)->where('selected', null)->where('can.create', true));
    $this->get(route('organization.branches.show', $branch))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('organization/branches/index')->where('selected.id', $branch->ulid)->where('selected.geofence.radiusM', 150));
    $this->get(route('organization.departments.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('organization/departments/index')->has('departments', 2)->where('departments.1.parentId', $root->ulid));
    $this->get(route('organization.positions.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('organization/positions/index')->where('positions.data.0.departmentName.en', $root->getTranslation('name', 'en')));
    $this->get(route('organization.catalogs.index', 'grades'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('organization/catalogs/index')->where('kind', 'grades'));
});

it('shows organization read-only to roles without edit rights', function (): void {
    $this->actingAs(userWithRoles('branch_manager'));

    $this->get(route('organization.branches.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('can.create', false)->where('can.update', false));
});
