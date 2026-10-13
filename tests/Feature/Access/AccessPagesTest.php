<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RolePermissionScope;
use App\Domain\Access\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    seedAccess();
    $this->admin = userWithRoles('hr_admin');
    $this->admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
});

function roleNamed(string $name): Role
{
    return Role::query()->where('name', $name)->sole();
}

it('lists roles for people with access.view only', function (): void {
    $this->actingAs($this->admin)->get(route('access.roles.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('access/roles/index')->has('roles', 12));

    $this->actingAs(userWithRoles('employee'))->get(route('access.roles.index'))->assertForbidden();
});

it('shows the matrix with grants, defaults and permissions', function (): void {
    $officer = roleNamed('hr_officer');

    $this->actingAs($this->admin)->get(route('access.roles.show', $officer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('access/roles/show')
            ->where('role.id', $officer->ulid)
            ->where('grants', fn ($grants): bool => $grants['people.view'] === 'branch')
            ->where('defaults', fn ($defaults): bool => $defaults['people.view'] === 'branch')
            ->where('can.update', true)
            ->where('can.delete', false));
});

it('saves matrix changes over HTTP and never exposes internal ids', function (): void {
    $trainer = roleNamed('trainer');

    $this->actingAs($this->admin)
        ->put(route('access.roles.grants.update', $trainer), ['grants' => ['training.view' => 'all', 'people.view' => 'department']])
        ->assertRedirect();

    $scopes = RolePermissionScope::query()->where('role_id', $trainer->id)->with('permission')->get()
        ->mapWithKeys(fn (RolePermissionScope $scope): array => [$scope->permission?->name => $scope->scope]);

    expect($scopes->all())->toEqual(['training.view' => Scope::All, 'people.view' => Scope::Department]);

    $response = $this->get(route('access.roles.index'));
    expect($response->getContent())->not->toMatch('/"id":\s*\d+[,}]/');
});

it('rejects invalid scopes', function (): void {
    $this->actingAs($this->admin)
        ->put(route('access.roles.grants.update', roleNamed('trainer')), ['grants' => ['training.view' => 'galaxy']])
        ->assertSessionHasErrors('grants.training.view');
});

it('clones, edits and refuses to delete seeded roles', function (): void {
    $this->actingAs($this->admin)
        ->post(route('access.roles.clone', roleNamed('employee')), ['label' => ['en' => 'Contractor', 'ar' => 'متعاقد']])
        ->assertRedirect(route('access.roles.show', Role::query()->where('name', 'contractor')->sole()));

    $this->patch(route('access.roles.update', roleNamed('employee')), [
        'label' => ['en' => 'Staff member', 'ar' => 'موظف'],
        'requiresTwoFactor' => true,
        'homeLayout' => 'employee',
    ])->assertRedirect();

    expect(roleNamed('employee')->getTranslation('label', 'en'))->toBe('Staff member')
        ->and(roleNamed('employee')->requires_2fa)->toBeTrue();

    $this->delete(route('access.roles.destroy', roleNamed('employee')))->assertSessionHasErrors('error');
});

it('saves sensitive field access', function (): void {
    $this->actingAs($this->admin)
        ->put(route('access.roles.fields.update', roleNamed('trainer')), ['fields' => ['employee.salary' => 'read', 'not.a.field' => 'write']])
        ->assertRedirect();

    expect(roleNamed('trainer')->fieldPermissions()->pluck('field_key')->all())->toBe(['employee.salary']);
});

it('lists users, assigns roles and previews what they can do', function (): void {
    $target = User::factory()->create(['name' => 'Dana Al-Otaibi']);

    $this->actingAs($this->admin)->get(route('access.users.index', ['search' => 'dana']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('access/users/index')->has('users.data', 1)->where('users.data.0.id', $target->ulid));

    $this->put(route('access.users.roles.update', $target), ['roles' => ['employee', 'team_lead']])->assertRedirect();
    expect($target->fresh()?->getRoleNames()->sort()->values()->all())->toBe(['employee', 'team_lead']);

    $this->get(route('access.users.preview', $target))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('access/users/preview')
            ->where('matrix.leave.approve', 'team_tree')
            ->where('fields', fn ($fields): bool => $fields['employee.salary'] === null));
});
