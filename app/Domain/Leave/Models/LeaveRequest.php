<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time away taken through a request. While pending it holds its days against the balance (D64);
 * approval debits the ledger. A request never spans two leave years.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property LeaveRequestKind $kind
 * @property int|null $leave_type_id
 * @property int|null $request_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string|null $half_day morning|afternoon
 * @property string|null $from_time
 * @property string|null $to_time
 * @property int|null $minutes
 * @property float $amount days (or hours for hour-based types)
 * @property LeaveRequestStatus $status
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class LeaveRequest extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<EmployeeRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    public function year(): int
    {
        return $this->starts_on->year;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LeaveRequestKind::class,
            'status' => LeaveRequestStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'amount' => 'float',
            'minutes' => 'integer',
        ];
    }
}
