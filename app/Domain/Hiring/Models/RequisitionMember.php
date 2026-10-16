<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Enums\MemberRole;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_requisition_id
 * @property int $user_id
 * @property MemberRole $role
 */
#[Guarded(['id'])]
final class RequisitionMember extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<JobRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => MemberRole::class];
    }
}
