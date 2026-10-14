<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Access\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, human-readable history of an instance (the request detail timeline).
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property int|null $workflow_step_id
 * @property string $event
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id'])]
#[Table(name: 'workflow_timeline')]
final class WorkflowTimelineEntry extends Model
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
