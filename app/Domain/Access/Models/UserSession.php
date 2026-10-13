<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A signed-in browser/device, for "Active sessions" (brief §6). The session stores this row's
 * ULID, which survives session-id regeneration; revoking the row signs that browser out on its
 * next request.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $last_seen_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'ip', 'user_agent', 'last_seen_at', 'revoked_at'])]
final class UserSession extends Model
{
    public const string SESSION_KEY = 'mawred.user_session';

    use HasPublicUlid;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
