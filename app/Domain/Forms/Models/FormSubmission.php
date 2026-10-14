<?php

declare(strict_types=1);

namespace App\Domain\Forms\Models;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\People\Models\Employee;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Answers to one form version. `data` is encrypted at rest (it can hold personal data) and keyed
 * by field key; files are listed there and stored as `FormUpload`s attached to the submission.
 *
 * @property int $id
 * @property string $ulid
 * @property int $form_id
 * @property int $form_version_id
 * @property array<string, mixed> $data
 * @property SubmissionSource $source
 * @property int|null $submitted_by
 * @property int|null $employee_id
 * @property string|null $ip_hash
 * @property string $locale
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class FormSubmission extends Model
{
    use HasPublicUlid;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return HasMany<FormUpload, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(FormUpload::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['data' => 'encrypted:array', 'source' => SubmissionSource::class, 'created_at' => 'immutable_datetime'];
    }
}
