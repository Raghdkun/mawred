<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Policies\Models\LeaveType;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement on a leave balance (append-only). The balance for a type and leave year is the
 * sum of its entries; pending requests are held on top (D64).
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property LedgerKind $kind
 * @property float $amount
 * @property CarbonImmutable $effective_on
 * @property CarbonImmutable|null $expires_on
 * @property int|null $leave_request_id
 * @property string|null $note
 * @property int|null $actor_user_id
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
#[Table(name: 'leave_ledger')]
final class LeaveLedgerEntry extends Model
{
    use HasPublicUlid;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

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
        return [
            'kind' => LedgerKind::class,
            'amount' => 'float',
            'year' => 'integer',
            'effective_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
