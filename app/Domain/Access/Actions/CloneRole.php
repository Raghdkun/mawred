<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies a role's grants, scopes and field permissions into a new custom role (screen 38).
 */
final readonly class CloneRole
{
    public function __construct(private SyncRoleGrants $grants, private SyncFieldPermissions $fields) {}

    /**
     * @param  array{en: string, ar: string}  $label
     */
    public function handle(Role $source, array $label): Role
    {
        if ($source->is_super_admin) {
            throw new DomainRuleViolation('access.super_admin_not_clonable', __('The Super Admin role cannot be cloned.'));
        }

        return DB::transaction(function () use ($source, $label): Role {
            $clone = new Role(['name' => $this->uniqueName($label['en']), 'guard_name' => 'web']);
            $clone->forceFill([
                'label' => $label,
                'description' => $source->getTranslations('description'),
                'is_system' => false,
                'is_super_admin' => false,
                'api_only' => $source->api_only,
                'requires_2fa' => $source->requires_2fa,
                'home_layout' => $source->home_layout,
                'rank' => $source->rank,
            ])->save();

            $grants = [];
            foreach ($source->permissionScopes()->with('permission')->get() as $scope) {
                if ($scope->permission instanceof Permission) {
                    $grants[$scope->permission->name] = $scope->scope;
                }
            }

            $fields = [];
            foreach ($source->fieldPermissions()->get() as $field) {
                $fields[$field->field_key] = $field->access;
            }

            $this->grants->handle($clone, $grants);
            $this->fields->handle($clone, $fields);

            return $clone;
        });
    }

    private function uniqueName(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'custom_role';
        $name = $base;
        $suffix = 2;

        while (Role::query()->where('name', $name)->exists()) {
            $name = "{$base}_{$suffix}";
            $suffix++;
        }

        return $name;
    }
}
