<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A custom report saved from the builder (D118, D121): a dataset and a typed definition (columns,
 * filters, grouping, chart). It runs in each viewer's own scope (D119).
 *
 * @property int $id
 * @property string $ulid
 * @property int $owner_user_id
 * @property string $name
 * @property string|null $description
 * @property string $dataset
 * @property array<string, mixed> $definition
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Guarded(['id', 'ulid'])]
final class SavedReport extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** The report key a saved report runs under in the viewer and exports. */
    public const string KEY_PREFIX = 'custom.';

    public function reportKey(): string
    {
        return self::KEY_PREFIX.$this->ulid;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<SavedReportShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(SavedReportShare::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
