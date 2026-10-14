<?php

declare(strict_types=1);

namespace App\Domain\Forms\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Forms\Enums\VersionStatus;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A form definition (ADR-0008). Its schema lives in versions: one draft being edited and the
 * published version people fill in. Submissions pin the version they were made against.
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $name
 * @property array<string, string>|string|null $description
 * @property string|null $category
 * @property int|null $published_version_id
 * @property bool $is_public
 * @property string|null $public_slug
 * @property array{successMessage?: array<string, string>, closesAt?: string|null}|null $public_settings
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class Form extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = ['description' => null, 'category' => null, 'is_public' => false, 'public_slug' => null, 'public_settings' => null, 'published_version_id' => null];

    /**
     * @return HasMany<FormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'published_version_id');
    }

    /**
     * @return HasOne<FormVersion, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(FormVersion::class)->where('status', VersionStatus::Draft->value);
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'public_settings' => 'array'];
    }
}
