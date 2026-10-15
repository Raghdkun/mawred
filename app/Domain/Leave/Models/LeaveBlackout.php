<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * Dates when leave can't be taken (stock count, peak season). Applies to everyone, a branch, or
 * a department and the departments under it; to every leave type or only some.
 *
 * @property int $id
 * @property string $ulid
 * @property array<string, string>|string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int|null $branch_id
 * @property int|null $department_id
 * @property list<int>|null $leave_type_ids
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class LeaveBlackout extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

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
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'leave_type_ids' => 'array'];
    }
}
