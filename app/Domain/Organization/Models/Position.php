<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use App\Support\Search\HasSearchText;
use Database\Factories\Organization\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $title
 * @property int|null $department_id
 * @property int|null $grade_id
 * @property bool $is_active
 */
#[UseFactory(PositionFactory::class)]
#[Guarded(['id', 'ulid', 'search_text'])]
final class Position extends Model
{
    use Auditable;

    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    use HasPublicUlid;
    use HasSearchText;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['title'];

    /** @var array<string, mixed> */
    protected $attributes = ['department_id' => null, 'grade_id' => null, 'is_active' => true];

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function searchableValues(): array
    {
        return [$this->code, $this->getTranslations('title')];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
