<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Hiring\Enums\StageKind;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A column on a requisition's board. Stages without a requisition form the default template
 * that each new requisition copies.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $job_requisition_id
 * @property array<string, string>|string $name
 * @property StageKind $kind
 * @property int $position
 * @property array{message?: array{enabled: bool, subject: array<string, string>, body: array<string, string>}, booking?: array{enabled: bool, duration: int, panel: list<string>, title: string}}|null $automation stage automations (D93)
 */
#[Guarded(['id', 'ulid'])]
final class PipelineStage extends Model
{
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /**
     * @return BelongsTo<JobRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => StageKind::class, 'position' => 'integer', 'automation' => 'array'];
    }
}
