<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Access\Models\User;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\DecisionChannel;
use App\Domain\Workflows\Enums\TaskKind;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Human work on a step: an approval or a to-do. Delegation and escalation change the assignee
 * and keep the original for the record.
 *
 * @property int $id
 * @property string $ulid
 * @property int $workflow_instance_id
 * @property int $workflow_step_id
 * @property TaskKind $kind
 * @property int $assignee_user_id
 * @property int $original_assignee_user_id
 * @property int|null $delegated_from_user_id
 * @property TaskStatus $status
 * @property Decision|null $decision
 * @property string|null $comment
 * @property DecisionChannel|null $decided_via
 * @property int|null $decided_by_user_id
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $remind_at
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable|null $escalated_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class WorkflowTask extends Model
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
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function owner(): WorkflowInstance
    {
        return $this->instance ?? WorkflowInstance::query()->findOrFail($this->workflow_instance_id);
    }

    public function ownerStep(): WorkflowStep
    {
        return $this->step ?? WorkflowStep::query()->findOrFail($this->workflow_step_id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TaskKind::class,
            'status' => TaskStatus::class,
            'decision' => Decision::class,
            'decided_via' => DecisionChannel::class,
            'decided_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'remind_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
