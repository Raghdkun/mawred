<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use App\Support\Search\HasSearchText;
use Database\Factories\Organization\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A node in the unlimited-depth department tree. `path` is an ltree of ancestor ids maintained
 * by SaveDepartment / MoveDepartment, never by hand.
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $name
 * @property int|null $parent_id
 * @property string $path
 * @property int $depth
 * @property int|null $head_employee_id
 * @property int|null $cost_center_id
 * @property bool $is_active
 */
#[UseFactory(DepartmentFactory::class)]
#[Guarded(['id', 'ulid', 'search_text', 'path', 'depth'])]
final class Department extends Model
{
    use Auditable;

    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use HasPublicUlid;
    use HasSearchText;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'parent_id' => null,
        'head_employee_id' => null,
        'cost_center_id' => null,
        'depth' => 0,
        'path' => '',
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Every department in this one's subtree, itself included.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withinSubtree(Builder $query, self $root): void
    {
        $query->whereRaw('path <@ ?::ltree', [$root->path]);
    }

    public function searchableValues(): array
    {
        return [$this->code, $this->getTranslations('name')];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'depth' => 'integer'];
    }
}
