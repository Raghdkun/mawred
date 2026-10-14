<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Workflows\Enums\VersionStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workflow graph (`mawred.workflow/v1`). Node positions live in `layout`, outside the checksum.
 *
 * @property int $id
 * @property string $ulid
 * @property int $workflow_definition_id
 * @property int $number
 * @property VersionStatus $status
 * @property array<string, mixed> $graph
 * @property array<string, mixed>|null $layout
 * @property string|null $checksum
 * @property CarbonImmutable|null $published_at
 * @property int|null $published_by
 */
#[Guarded(['id', 'ulid'])]
final class WorkflowVersion extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<WorkflowDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => VersionStatus::class, 'graph' => 'array', 'layout' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
