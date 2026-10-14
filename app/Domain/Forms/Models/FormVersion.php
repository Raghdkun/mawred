<?php

declare(strict_types=1);

namespace App\Domain\Forms\Models;

use App\Domain\Forms\Enums\VersionStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a form's schema. Published versions are frozen; editing makes a new draft.
 *
 * @property int $id
 * @property string $ulid
 * @property int $form_id
 * @property int $number
 * @property VersionStatus $status
 * @property array<string, mixed> $schema
 * @property CarbonImmutable|null $published_at
 * @property int|null $published_by
 */
#[Guarded(['id', 'ulid'])]
final class FormVersion extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => VersionStatus::class, 'schema' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
