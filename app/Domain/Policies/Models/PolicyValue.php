<?php

declare(strict_types=1);

namespace App\Domain\Policies\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One key's value in a set over a date range. Rows are closed and replaced, never edited, so the
 * value on any past date stays reproducible.
 *
 * @property int $id
 * @property int $policy_set_id
 * @property string $key
 * @property array<string, mixed> $value
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 */
#[Guarded(['id'])]
final class PolicyValue extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = ['effective_to' => null, 'reason' => null, 'created_by' => null];

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
        return ['value' => 'array', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }
}
