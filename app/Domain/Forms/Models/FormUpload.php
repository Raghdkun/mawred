<?php

declare(strict_types=1);

namespace App\Domain\Forms\Models;

use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file uploaded while filling in a form (ADR-0008): private, expires after 24 hours unless a
 * submission claims it. Unclaimed uploads are pruned with their files.
 *
 * @property int $id
 * @property string $ulid
 * @property int $form_id
 * @property string $field_key
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $user_id
 * @property string|null $session_hash
 * @property int|null $form_submission_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class FormUpload extends Model
{
    use HasPublicUlid;
    use Prunable;

    public const string DISK = 'local';

    public const int TTL_HOURS = 24;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->whereNull('form_submission_id')->where('expires_at', '<', now());
    }

    protected function pruning(): void
    {
        Storage::disk(self::DISK)->delete($this->path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer', 'expires_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
