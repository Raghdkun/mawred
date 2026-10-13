<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\AnchorType;
use App\Domain\Audit\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Narrows a user's branch/department-scoped grants from one role to specific units (D5).
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property AnchorType $anchor_type
 * @property int $anchor_id
 */
#[Fillable(['user_id', 'role_id', 'anchor_type', 'anchor_id'])]
final class RoleAssignmentAnchor extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['anchor_type' => AnchorType::class];
    }
}
