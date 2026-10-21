<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Access\Models\User;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One device a person gets push notifications on (D128).
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property string|null $user_agent
 * @property CarbonImmutable|null $last_used_at
 */
#[Guarded(['id', 'ulid'])]
#[Hidden(['public_key', 'auth_token'])]
final class PushSubscription extends Model
{
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
            'public_key' => 'encrypted',
            'auth_token' => 'encrypted',
            'last_used_at' => 'immutable_datetime',
        ];
    }
}
