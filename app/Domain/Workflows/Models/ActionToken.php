<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\DecisionChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use link approval (ADR-0009). Only the sha256 of the token is stored.
 *
 * @property int $id
 * @property int $workflow_task_id
 * @property string $token_hash
 * @property Decision $decision
 * @property DecisionChannel $channel
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 */
#[Guarded(['id'])]
final class ActionToken extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<WorkflowTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'workflow_task_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['decision' => Decision::class, 'channel' => DecisionChannel::class, 'expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime'];
    }
}
