<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\Access\Models\User;
use App\Domain\People\Enums\ImportStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A bulk import. The source file and the error report live on the private disk.
 *
 * @property int $id
 * @property string $ulid
 * @property string $type
 * @property ImportStatus $status
 * @property string $original_name
 * @property list<string> $headers
 * @property array<array-key, string> $mapping column index => field key
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $to_create
 * @property int $to_update
 * @property int $processed_rows
 * @property int $created_rows
 * @property int $updated_rows
 * @property int $failed_rows
 * @property string|null $failure
 * @property int|null $created_by
 * @property CarbonImmutable|null $validated_at
 * @property CarbonImmutable|null $committed_at
 * @property CarbonImmutable|null $created_at
 */
#[Guarded(['id', 'ulid'])]
final class Import extends Model implements HasMedia
{
    use HasPublicUlid;
    use InteractsWithMedia;

    public const string TYPE_EMPLOYEES = 'employees';

    /** @var list<string> */
    public const array SOURCE_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'application/csv',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'headers' => '[]',
        'mapping' => '{}',
        'failure' => null,
        'validated_at' => null,
        'committed_at' => null,
    ];

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('source')->singleFile()->useDisk('local');
        $this->addMediaCollection('error_report')->singleFile()->useDisk('local');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'headers' => 'array',
            'mapping' => 'array',
            'validated_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
