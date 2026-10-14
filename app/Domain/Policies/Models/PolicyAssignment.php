<?php

declare(strict_types=1);

namespace App\Domain\Policies\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A set attached to one level and target over a date range (one set per target at a time).
 *
 * @property int $id
 * @property string $ulid
 * @property int $policy_set_id
 * @property PolicyLevel $level
 * @property int|null $target_id
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $reason
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class PolicyAssignment extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = ['target_id' => null, 'effective_to' => null, 'reason' => null, 'created_by' => null];

    /**
     * @return BelongsTo<PolicySet, $this>
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(PolicySet::class, 'policy_set_id')->withTrashed();
    }

    public function coversDate(string $date): bool
    {
        return $this->effective_from->toDateString() <= $date && ($this->effective_to === null || $this->effective_to->toDateString() >= $date);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => PolicyLevel::class, 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
