<?php

declare(strict_types=1);

namespace App\Domain\Requests\Models;

use App\Domain\Access\Models\User;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment on a request's thread (handoff 12).
 *
 * @property int $id
 * @property string $ulid
 * @property int $request_id
 * @property int|null $user_id
 * @property string $body
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class RequestComment extends Model
{
    use HasPublicUlid;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
