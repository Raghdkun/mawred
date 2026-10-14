<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Workflows\Enums\StepStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One activation of a node. Unique per (instance, node, iteration, branch), so a retried job can
 * never activate the same node twice; side effects use the step ULID as their idempotency key.
 *
 * @property int $id
 * @property string $ulid
 * @property int $workflow_instance_id
 * @property string $node_id
 * @property string $node_type
 * @property int $iteration
 * @property string $branch_key
 * @property StepStatus $status
 * @property int $attempts
 * @property CarbonImmutable|null $available_at
 * @property CarbonImmutable|null $due_at
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
#[Guarded(['id', 'ulid'])]
final class WorkflowStep extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /**
     * @return HasMany<WorkflowTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function owner(): WorkflowInstance
    {
        return $this->instance ?? WorkflowInstance::query()->findOrFail($this->workflow_instance_id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StepStatus::class,
            'available_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
