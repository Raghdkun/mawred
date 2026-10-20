<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person or role a saved report is shared with (D119).
 *
 * @property int $id
 * @property int $saved_report_id
 * @property int|null $user_id
 * @property int|null $role_id
 */
#[Guarded(['id'])]
final class SavedReportShare extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
