<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\DefaultRoles;
use Illuminate\Support\Facades\DB;

/**
 * Creates the seeded roles with their default grants (brief §6). Existing roles keep their
 * current grants unless `$resetGrants` is set, so re-running never overwrites HR's changes.
 */
final readonly class SeedDefaultRoles
{
    public function __construct(
        private SyncPermissionCatalog $catalog,
        private SyncRoleGrants $grants,
        private SyncFieldPermissions $fields,
    ) {}

    public function handle(bool $resetGrants = false): void
    {
        $this->catalog->handle();

        DB::transaction(function () use ($resetGrants): void {
            foreach (DefaultRoles::definitions() as $name => $definition) {
                $existing = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

                $role = $existing ?? new Role(['name' => $name, 'guard_name' => 'web']);
                $role->forceFill([
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'is_system' => true,
                    'is_super_admin' => $definition['super_admin'] ?? false,
                    'api_only' => $definition['api_only'] ?? false,
                    'requires_2fa' => $definition['requires_2fa'],
                    'home_layout' => $definition['home'],
                    'rank' => $definition['rank'],
                ])->save();

                // The Super Admin holds every permission implicitly; it has no stored grants.
                if (! $role->is_super_admin && (! $existing instanceof Role || $resetGrants)) {
                    $this->grants->handle($role, $definition['grants']);
                    $this->fields->handle($role, $definition['fields'] ?? []);
                }
            }
        });
    }
}
