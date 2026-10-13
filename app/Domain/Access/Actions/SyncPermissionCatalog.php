<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Permission;
use App\Domain\Access\Support\AccessEpoch;
use App\Domain\Access\Support\PermissionCatalog;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the permissions table match PermissionCatalog. Idempotent; run by every seeder and deploy.
 */
final readonly class SyncPermissionCatalog
{
    public function __construct(private AccessEpoch $epoch, private PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        foreach (PermissionCatalog::all() as $name) {
            [$module, $action] = PermissionCatalog::split($name);

            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => $module, 'action' => $action],
            );
        }

        Permission::query()->whereNotIn('name', PermissionCatalog::all())->delete();

        $this->registrar->forgetCachedPermissions();
        $this->epoch->bump();
    }
}
