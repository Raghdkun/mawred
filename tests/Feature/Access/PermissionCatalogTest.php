<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SeedDefaultRoles;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\DefaultRoles;
use App\Domain\Access\Support\PermissionCatalog;
use Illuminate\Contracts\Database\Query\Builder;

it('seeds every catalog permission once, idempotently', function (): void {
    seedAccess();
    seedAccess();

    expect(Permission::query()->count())->toBe(count(PermissionCatalog::all()))
        ->and(Permission::query()->where('name', 'leave.approve')->value('module'))->toBe('leave');
});

it('seeds the twelve roles from the brief with bilingual labels', function (): void {
    seedAccess();

    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(array_keys(DefaultRoles::definitions()))->sort()->values()->all())
        ->and(Role::query()->count())->toBe(12);

    $officer = Role::query()->where('name', 'hr_officer')->sole();

    expect($officer->getTranslation('label', 'ar'))->toBe('أخصائي الموارد البشرية')
        ->and($officer->requires_2fa)->toBeTrue()
        ->and($officer->ulid)->toHaveLength(26);
});

it('does not overwrite grants HR has changed when re-seeded', function (): void {
    seedAccess();
    $employee = Role::query()->where('name', 'employee')->sole();
    $employee->permissionScopes()->delete();
    $employee->syncPermissions([]);

    resolve(SeedDefaultRoles::class)->handle();

    expect($employee->fresh()?->permissions)->toBeEmpty();

    resolve(SeedDefaultRoles::class)->handle(resetGrants: true);

    expect($employee->fresh()?->permissions)->not->toBeEmpty();
});

it('stores unscoped permissions with the all scope', function (): void {
    expect(PermissionCatalog::isScoped('forms.update'))->toBeFalse()
        ->and(PermissionCatalog::isScoped('leave.view'))->toBeTrue();

    seedAccess();
    $admin = Role::query()->where('name', 'hr_admin')->sole();

    expect($admin->permissionScopes()->whereHas('permission', fn (Builder $q) => $q->where('name', 'forms.update'))->sole()->scope)
        ->toBe(Scope::All);
});
