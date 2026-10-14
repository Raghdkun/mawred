<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "While I'm away, my approvals go to …" for a date range, optionally only for some workflows.
 *
 * @property int $id
 * @property string $ulid
 * @property int $from_user_id
 * @property int $to_user_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property list<int>|null $definition_ids
 * @property string|null $reason
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class Delegation extends Model
{
    use Auditable;
    use HasPublicUlid;
    use SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'definition_ids' => 'array'];
    }
}
