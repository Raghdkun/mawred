<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Access;

use App\Domain\Access\Actions\CloneRole;
use App\Domain\Access\Actions\DeleteRole;
use App\Domain\Access\Actions\SyncFieldPermissions;
use App\Domain\Access\Actions\SyncRoleGrants;
use App\Domain\Access\Actions\UpdateRoleDetails;
use App\Domain\Access\Data\PermissionModuleData;
use App\Domain\Access\Data\RoleSummaryData;
use App\Domain\Access\Data\UpdateRoleGrantsData;
use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\DefaultRoles;
use App\Domain\Access\Support\PermissionCatalog;
use App\Domain\Access\Support\SensitiveFieldRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleDetailsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles & permissions (handoff screen 38).
 */
final class RolesController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('access.view');

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('rank')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): RoleSummaryData => RoleSummaryData::fromRole($role, $this->count($role, 'users_count'), $this->count($role, 'permissions_count')));

        return Inertia::render('access/roles/index', [
            'roles' => $roles,
            'can' => [
                'create' => Gate::allows('access.create'),
            ],
        ]);
    }

    public function show(Role $role, SensitiveFieldRegistry $sensitiveFields): Response
    {
        Gate::authorize('access.view');

        $role->loadCount(['users', 'permissions']);

        $grants = [];
        foreach ($role->permissionScopes()->with('permission')->get() as $scope) {
            if ($scope->permission !== null) {
                $grants[$scope->permission->name] = $scope->scope->value;
            }
        }

        $fields = [];
        foreach ($role->fieldPermissions()->get() as $field) {
            $fields[$field->field_key] = $field->access->value;
        }

        $modules = [];
        foreach (PermissionCatalog::MODULES as $module => $definition) {
            $modules[] = new PermissionModuleData($module, $definition['actions'], $definition['scoped']);
        }

        return Inertia::render('access/roles/show', [
            'role' => RoleSummaryData::fromRole($role, $this->count($role, 'users_count'), $this->count($role, 'permissions_count')),
            'modules' => $modules,
            'actions' => PermissionCatalog::ACTIONS,
            'scopes' => array_map(fn (Scope $scope): string => $scope->value, Scope::cases()),
            'homeLayouts' => array_map(fn (HomeLayout $layout): string => $layout->value, HomeLayout::cases()),
            'grants' => (object) $grants,
            // Seeded roles show an "overridden" dot where HR changed the default (DSData permNote).
            'defaults' => $role->is_system ? (object) array_map(
                fn (Scope $scope): string => $scope->value,
                DefaultRoles::definitions()[$role->name]['grants'] ?? [],
            ) : null,
            'sensitiveFields' => $sensitiveFields->keys(),
            'fieldLabels' => (object) $sensitiveFields->labels(),
            'fields' => (object) $fields,
            'can' => [
                'update' => Gate::allows('access.update') && ! $role->is_super_admin,
                'create' => Gate::allows('access.create') && ! $role->is_super_admin,
                'delete' => Gate::allows('access.delete') && ! $role->is_system,
            ],
        ]);
    }

    public function update(RoleDetailsRequest $request, Role $role, UpdateRoleDetails $update): RedirectResponse
    {
        Gate::authorize('access.update');

        $update->handle(
            $role,
            $request->label(),
            $request->description(),
            $request->boolean('requiresTwoFactor', $role->requires_2fa),
            $request->enum('homeLayout', HomeLayout::class) ?? $role->home_layout,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function updateGrants(UpdateRoleGrantsData $data, Role $role, SyncRoleGrants $sync): RedirectResponse
    {
        Gate::authorize('access.update');

        $diff = $sync->handle($role, $data->toScopes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions saved: :added added, :removed removed, :rescoped rescoped.', [
            'added' => count($diff['added']),
            'removed' => count($diff['removed']),
            'rescoped' => count($diff['rescoped']),
        ])]);

        return back();
    }

    public function updateFields(Request $request, Role $role, SyncFieldPermissions $sync, SensitiveFieldRegistry $sensitiveFields): RedirectResponse
    {
        Gate::authorize('access.update');

        $request->validate([
            'fields' => ['present', 'array'],
            'fields.*' => ['required', Rule::enum(FieldAccess::class)],
        ]);

        $requested = $request->array('fields');
        $fields = [];
        foreach ($sensitiveFields->keys() as $key) {
            $access = is_string($requested[$key] ?? null) ? FieldAccess::tryFrom($requested[$key]) : null;

            if ($access instanceof FieldAccess) {
                $fields[$key] = $access;
            }
        }

        $sync->handle($role, $fields);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field permissions saved.')]);

        return back();
    }

    public function clone(RoleDetailsRequest $request, Role $role, CloneRole $clone): RedirectResponse
    {
        Gate::authorize('access.create');

        $copy = $clone->handle($role, $request->label());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('access.roles.show', $copy);
    }

    public function destroy(Role $role, DeleteRole $delete): RedirectResponse
    {
        Gate::authorize('access.delete');

        $delete->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('access.roles.index');
    }

    private function count(Role $role, string $attribute): int
    {
        $value = $role->getAttribute($attribute);

        return is_numeric($value) ? (int) $value : 0;
    }
}
