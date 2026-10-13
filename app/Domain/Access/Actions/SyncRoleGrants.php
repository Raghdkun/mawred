<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RolePermissionScope;
use App\Domain\Access\Support\AccessEpoch;
use App\Domain\Access\Support\PermissionCatalog;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Replaces a role's permission grants and their scopes in one step (the matrix "Save").
 * Unscoped permissions are always stored with the `all` scope.
 */
final readonly class SyncRoleGrants
{
    public function __construct(private AccessEpoch $epoch, private PermissionRegistrar $registrar) {}

    /**
     * @param  array<string, Scope>  $grants  permission name => scope
     * @return array{added: list<string>, removed: list<string>, rescoped: list<string>}
     */
    public function handle(Role $role, array $grants): array
    {
        if ($role->is_super_admin) {
            throw new DomainRuleViolation('access.super_admin_immutable', __('The Super Admin role always has every permission.'));
        }

        foreach (array_keys($grants) as $permission) {
            if (! PermissionCatalog::exists($permission)) {
                throw new DomainRuleViolation('access.unknown_permission', __('Unknown permission :permission.', ['permission' => $permission]));
            }
        }

        $normalised = [];
        foreach ($grants as $permission => $scope) {
            $normalised[$permission] = PermissionCatalog::isScoped($permission) ? $scope : Scope::All;
        }

        return DB::transaction(function () use ($role, $normalised): array {
            $permissions = Permission::query()->whereIn('name', array_keys($normalised))->pluck('id', 'name');
            $before = RolePermissionScope::query()
                ->where('role_id', $role->id)
                ->join('permissions', 'permissions.id', '=', 'role_permission_scopes.permission_id')
                ->toBase()
                ->pluck('role_permission_scopes.scope', 'permissions.name')
                ->all();

            $role->syncPermissions(array_keys($normalised));

            RolePermissionScope::query()->where('role_id', $role->id)->delete();
            foreach ($normalised as $permission => $scope) {
                RolePermissionScope::query()->create([
                    'role_id' => $role->id,
                    'permission_id' => $permissions[$permission],
                    'scope' => $scope,
                ]);
            }

            $added = array_values(array_diff(array_keys($normalised), array_keys($before)));
            $removed = array_values(array_diff(array_keys($before), array_keys($normalised)));
            $rescoped = array_values(array_filter(
                array_intersect(array_keys($before), array_keys($normalised)),
                fn (string $permission): bool => $before[$permission] !== $normalised[$permission]->value,
            ));

            activity('access')
                ->performedOn($role)
                ->event('grants.synced')
                ->withProperties(['added' => $added, 'removed' => $removed, 'rescoped' => $rescoped])
                ->log('Role permissions updated');

            $this->registrar->forgetCachedPermissions();
            $this->epoch->bump();

            return ['added' => $added, 'removed' => $removed, 'rescoped' => $rescoped];
        });
    }
}
