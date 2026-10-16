<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ClaimStatus;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone asking for an open shift (D73).
 *
 * @property int $id
 * @property string $ulid
 * @property int $roster_shift_id
 * @property int $employee_id
 * @property ClaimStatus $status
 */
#[Guarded(['id', 'ulid'])]
final class OpenShiftClaim extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<RosterShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(RosterShift::class, 'roster_shift_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ClaimStatus::class];
    }
}
