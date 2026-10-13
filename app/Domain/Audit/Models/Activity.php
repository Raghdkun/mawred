<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * One audit entry (ADR-0006). Append-only: the application never updates or deletes rows.
 *
 * @property string|null $actor_type
 * @property string|null $channel
 * @property int|null $impersonator_id
 * @property string|null $ip
 * @property string|null $user_agent_hash
 * @property string|null $request_id
 */
final class Activity extends SpatieActivity
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }
}
