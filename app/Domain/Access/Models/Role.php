<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $guard_name
 * @property array<string, string>|string|null $label
 * @property array<string, string>|string|null $description
 * @property bool $is_system
 * @property bool $is_super_admin
 * @property bool $api_only
 * @property bool $requires_2fa
 * @property HomeLayout $home_layout
 * @property int $rank
 */
final class Role extends SpatieRole
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['label', 'description'];

    /**
     * @return HasMany<RolePermissionScope, $this>
     */
    public function permissionScopes(): HasMany
    {
        return $this->hasMany(RolePermissionScope::class);
    }

    /**
     * @return HasMany<FieldPermission, $this>
     */
    public function fieldPermissions(): HasMany
    {
        return $this->hasMany(FieldPermission::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_super_admin' => 'boolean',
            'api_only' => 'boolean',
            'requires_2fa' => 'boolean',
            'home_layout' => HomeLayout::class,
            'rank' => 'integer',
        ];
    }
}
