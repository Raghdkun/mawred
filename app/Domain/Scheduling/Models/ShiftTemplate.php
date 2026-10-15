<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Organization\Models\Branch;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A reusable shift (e.g. Morning 07:00–15:00). Ends before or at its start run past midnight and
 * belong to the day they start on (ADR-0004). Branch-only, or shared when branch is null.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $branch_id
 * @property string $code
 * @property array<string, string>|string $name
 * @property string $starts_at H:i:s
 * @property string $ends_at H:i:s
 * @property int $break_minutes
 * @property int $color chart token 1–8
 * @property bool $is_active
 */
#[Guarded(['id', 'ulid'])]
final class ShiftTemplate extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['break_minutes' => 'integer', 'color' => 'integer', 'is_active' => 'boolean'];
    }
}
