<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history of an application: stage moves, rejections, notes.
 *
 * @property int $id
 * @property int $job_application_id
 * @property ApplicationEventKind $kind
 * @property int|null $from_stage_id
 * @property int|null $to_stage_id
 * @property int|null $actor_user_id
 * @property string|null $body
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id'])]
final class ApplicationEvent extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ApplicationEventKind::class, 'data' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
