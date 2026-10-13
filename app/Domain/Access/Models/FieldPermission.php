<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\FieldAccess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $role_id
 * @property string $field_key
 * @property FieldAccess $access
 */
#[Fillable(['role_id', 'field_key', 'access'])]
#[WithoutIncrementing]
final class FieldPermission extends Model
{
    protected $primaryKey = 'role_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['access' => FieldAccess::class];
    }
}
