<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\FieldPermission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\AccessEpoch;
use Illuminate\Support\Facades\DB;

final readonly class SyncFieldPermissions
{
    public function __construct(private AccessEpoch $epoch) {}

    /**
     * @param  array<string, FieldAccess>  $fields  field key => access (absent = none)
     */
    public function handle(Role $role, array $fields): void
    {
        DB::transaction(function () use ($role, $fields): void {
            FieldPermission::query()->where('role_id', $role->id)->delete();

            foreach ($fields as $key => $access) {
                FieldPermission::query()->create(['role_id' => $role->id, 'field_key' => $key, 'access' => $access]);
            }

            activity('access')
                ->performedOn($role)
                ->event('fields.synced')
                ->withProperties(['fields' => array_map(fn (FieldAccess $access): string => $access->value, $fields)])
                ->log('Role field permissions updated');
        });

        $this->epoch->bump();
    }
}
