<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Hiring\Enums\Recommendation;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One panelist's feedback on an interview (D90): a 1–4 rating per criterion, an overall
 * recommendation and a comment. Others see it only after submitting their own (D82).
 *
 * @property int $id
 * @property string $ulid
 * @property int $interview_id
 * @property int $user_id
 * @property array<string, array{rating: int, note: string|null}> $ratings criterion ULID => rating
 * @property Recommendation|null $recommendation
 * @property string|null $comment
 * @property CarbonImmutable|null $submitted_at
 */
#[Guarded(['id', 'ulid'])]
final class Scorecard extends Model
{
    use Auditable;
    use HasPublicUlid;

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

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
        return ['ratings' => 'array', 'recommendation' => Recommendation::class, 'submitted_at' => 'immutable_datetime'];
    }
}
