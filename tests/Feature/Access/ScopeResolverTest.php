<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SyncRoleGrants;
use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Audit\Models\Activity;
use App\Domain\Audit\Queries\AuditLogQuery;
use Illuminate\Support\Facades\Gate;

beforeEach(fn () => seedAccess());

function freshResolver(): ScopeResolver
{
    app()->forgetScopedInstances();

    return resolve(ScopeResolver::class);
}

it('answers ability checks through the Gate', function (): void {
    $employee = userWithRoles('employee');
    $admin = userWithRoles('hr_admin');
    $nobody = User::factory()->create();

    expect($employee->can('leave.create'))->toBeTrue()
        ->and($employee->can('leave.approve'))->toBeFalse()
        ->and($admin->can('integrations.configure'))->toBeTrue()
        ->and($nobody->can('leave.view'))->toBeFalse();
});

it('gives the Super Admin every permission and field', function (): void {
    $root = userWithRoles('super_admin');
    $access = freshResolver()->for($root);

    expect($access->isSuperAdmin)->toBeTrue()
        ->and($root->can('access.configure'))->toBeTrue()
        ->and($access->fieldAccess('employee.salary'))->toBe(FieldAccess::Write)
        ->and($access->widestScope('people.view'))->toBe(Scope::All);
});

it('unions grants across roles and keeps the widest scope', function (): void {
    $user = userWithRoles('employee', 'hr_officer');
    $access = freshResolver()->for($user);

    expect($access->widestScope('leave.view'))->toBe(Scope::Branch)
        ->and($access->grantsFor('leave.view'))->toHaveCount(2)
        ->and($access->requiresTwoFactor)->toBeTrue();
});

it('resolves the strongest field access across roles', function (): void {
    $user = userWithRoles('hr_officer');

    expect(freshResolver()->fieldAccess($user, 'employee.national_id'))->toBe(FieldAccess::Write)
        ->and(freshResolver()->fieldAccess($user, 'employee.salary'))->toBeNull();
});

describe('query scoping', function (): void {
    beforeEach(function (): void {
        $this->me = userWithRoles('employee');
        $this->other = User::factory()->create();
        Activity::query()->delete();

        activity('test')->causedBy($this->me)->log('mine');
        activity('test')->causedBy($this->other)->log('theirs');
    });

    it('limits the Own scope to the subject user', function (): void {
        $role = Role::query()->where('name', 'employee')->sole();
        resolve(SyncRoleGrants::class)->handle($role, ['audit.view' => Scope::Own]);

        $visible = resolve(AuditLogQuery::class)->visibleTo($this->me->fresh())->pluck('description')->all();

        expect($visible)->toBe(['mine']);
    });

    it('returns nothing without the permission', function (): void {
        expect(resolve(AuditLogQuery::class)->visibleTo($this->me)->count())->toBe(0);
    });

    it('returns everything with the All scope', function (): void {
        $auditor = userWithRoles('auditor');

        expect(resolve(AuditLogQuery::class)->visibleTo($auditor)->where('log_name', 'test')->pluck('description')->sort()->values()->all())
            ->toBe(['mine', 'theirs']);
    });

    it('fails closed for org-relative scopes until the org structure exists', function (): void {
        $role = Role::query()->where('name', 'employee')->sole();
        resolve(SyncRoleGrants::class)->handle($role, ['audit.view' => Scope::Branch]);

        expect(resolve(AuditLogQuery::class)->visibleTo($this->me->fresh())->count())->toBe(0);
    });

    it('ORs grants from several roles', function (): void {
        Role::query()->where('name', 'employee')->sole()->givePermissionTo([]);
        resolve(SyncRoleGrants::class)->handle(Role::query()->where('name', 'employee')->sole(), ['audit.view' => Scope::Own]);
        resolve(SyncRoleGrants::class)->handle(Role::query()->where('name', 'team_lead')->sole(), ['audit.view' => Scope::TeamTree]);
        $this->me->assignRole('team_lead');

        expect(freshResolver()->apply(Activity::query(), $this->me->fresh(), 'audit.view', SubjectBinding::user('causer_id'))->pluck('description')->all())
            ->toBe(['mine']);
    });

    it('checks single records with the same rules', function (): void {
        $role = Role::query()->where('name', 'employee')->sole();
        resolve(SyncRoleGrants::class)->handle($role, ['audit.view' => Scope::Own]);
        $resolver = freshResolver();

        $mine = Activity::query()->where('description', 'mine')->sole();
        $theirs = Activity::query()->where('description', 'theirs')->sole();
        $binding = SubjectBinding::user('causer_id');

        expect($resolver->allows($this->me, 'audit.view', $mine, $binding))->toBeTrue()
            ->and($resolver->allows($this->me, 'audit.view', $theirs, $binding))->toBeFalse();
    });
});

it('invalidates cached access when grants change', function (): void {
    $user = userWithRoles('employee');

    expect(freshResolver()->can($user, 'reports.view'))->toBeFalse();

    $role = Role::query()->where('name', 'employee')->sole();
    resolve(SyncRoleGrants::class)->handle($role, ['reports.view' => Scope::Own]);

    expect(freshResolver()->can($user, 'reports.view'))->toBeTrue();
});

it('opens operational dashboards only to system.view holders', function (): void {
    $admin = userWithRoles('hr_admin');
    $employee = userWithRoles('employee');

    expect(Gate::forUser($admin)->allows('viewPulse'))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('viewPulse'))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('viewHorizon'))->toBe(app()->isLocal());
});
