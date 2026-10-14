<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Workflows\Enums\VersionStatus;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A workflow (ADR-0009). Its graph lives in versions: one draft in the builder and the published
 * version new instances start on. Running instances keep the version they started with.
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $name
 * @property array<string, string>|string|null $description
 * @property string|null $category
 * @property int|null $published_version_id
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class WorkflowDefinition extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = ['description' => null, 'category' => null, 'published_version_id' => null];

    /**
     * @return HasMany<WorkflowVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'published_version_id');
    }

    /**
     * @return HasOne<WorkflowVersion, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class)->where('status', VersionStatus::Draft->value);
    }
}
