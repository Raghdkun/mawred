<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\Outcome;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One run of a workflow version for a subject (a request). `context` holds what the run reads:
 * the form answers (`field`), the requester, `ctx` and each node's decision (`steps`).
 *
 * @property int $id
 * @property string $ulid
 * @property int $workflow_version_id
 * @property int $workflow_definition_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property InstanceStatus $status
 * @property Outcome|null $outcome
 * @property array<string, mixed> $context
 * @property int|null $requester_user_id
 * @property int|null $requester_employee_id
 * @property int|null $parent_step_id
 * @property string|null $correlation_key
 * @property string|null $attention_reason
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 */
#[Guarded(['id', 'ulid'])]
final class WorkflowInstance extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    /**
     * @return BelongsTo<WorkflowDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id')->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    /**
     * @return HasMany<WorkflowTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class);
    }

    /**
     * @return HasMany<WorkflowTimelineEntry, $this>
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(WorkflowTimelineEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id')->withTrashed();
    }

    public function pinnedVersion(): WorkflowVersion
    {
        return $this->version ?? WorkflowVersion::query()->findOrFail($this->workflow_version_id);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [InstanceStatus::Running, InstanceStatus::Returned, InstanceStatus::Attention], true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InstanceStatus::class,
            'outcome' => Outcome::class,
            'context' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
