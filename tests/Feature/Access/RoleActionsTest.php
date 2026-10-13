<?php

declare(strict_types=1);

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Actions\CloneRole;
use App\Domain\Access\Actions\DeleteRole;
use App\Domain\Access\Actions\SyncRoleGrants;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignmentAnchor;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Audit\Models\Activity;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

beforeEach(fn () => seedAccess());

function role(string $name): Role
{
    return Role::query()->where('name', $name)->sole();
}

it('syncs grants, reports the diff and audits it', function (): void {
    $diff = resolve(SyncRoleGrants::class)->handle(role('trainer'), [
        'training.view' => Scope::All,
        'training.create' => Scope::Branch,
        'forms.view' => Scope::Own, // unscoped: stored as all
    ]);

    expect($diff['added'])->toContain('forms.view')
        ->and($diff['removed'])->toContain('calendar.view')
        ->and($diff['rescoped'])->toBe(['training.create']);

    $entry = Activity::query()->where('event', 'grants.synced')->latest('id')->first();
    expect($entry?->properties?->get('added'))->toContain('forms.view');
});

it('rejects unknown permissions and edits to the Super Admin', function (): void {
    expect(fn () => resolve(SyncRoleGrants::class)->handle(role('employee'), ['payroll.run' => Scope::All]))
        ->toThrow(DomainRuleViolation::class)
        ->and(fn () => resolve(SyncRoleGrants::class)->handle(role('super_admin'), []))
        ->toThrow(DomainRuleViolation::class);
});

it('clones a role with its scopes and field permissions', function (): void {
    $clone = resolve(CloneRole::class)->handle(role('hr_officer'), ['en' => 'Regional HR', 'ar' => 'موارد بشرية إقليمية']);

    expect($clone->name)->toBe('regional_hr')
        ->and($clone->is_system)->toBeFalse()
        ->and($clone->permissions()->count())->toBe(role('hr_officer')->permissions()->count())
        ->and($clone->fieldPermissions()->count())->toBe(role('hr_officer')->fieldPermissions()->count());
});

it('refuses to delete seeded or assigned roles', function (): void {
    expect(fn () => resolve(DeleteRole::class)->handle(role('employee')))->toThrow(DomainRuleViolation::class);

    $custom = resolve(CloneRole::class)->handle(role('employee'), ['en' => 'Contractor', 'ar' => 'متعاقد']);
    userWithRoles('contractor');

    expect(fn () => resolve(DeleteRole::class)->handle($custom))->toThrow(DomainRuleViolation::class);
});

it('assigns roles with anchors that narrow branch scopes', function (): void {
    $admin = userWithRoles('super_admin');
    $user = User::factory()->create();

    resolve(AssignRoles::class)->handle(Actor::user($admin), $user, [
        'employee' => [],
        'hr_officer' => ['branches' => [10, 20]],
    ]);

    app()->forgetScopedInstances();
    $grants = resolve(ScopeResolver::class)->for($user->fresh())->grantsFor('people.update');

    expect($user->fresh()?->getRoleNames()->sort()->values()->all())->toBe(['employee', 'hr_officer'])
        ->and(RoleAssignmentAnchor::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and($grants[0]->branchIds)->toBe([10, 20]);
});

it('only lets a Super Admin grant the Super Admin role', function (): void {
    $hrAdmin = userWithRoles('hr_admin');
    $target = User::factory()->create();

    expect(fn () => resolve(AssignRoles::class)->handle(Actor::user($hrAdmin), $target, ['super_admin' => []]))
        ->toThrow(DomainRuleViolation::class);
});
