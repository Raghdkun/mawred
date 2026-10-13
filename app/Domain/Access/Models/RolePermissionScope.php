<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\Scope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $role_id
 * @property int $permission_id
 * @property Scope $scope
 */
#[Fillable(['role_id', 'permission_id', 'scope'])]
#[WithoutIncrementing]
final class RolePermissionScope extends Model
{
    protected $primaryKey = 'role_id';

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope' => Scope::class];
    }
}
