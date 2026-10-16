<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Attendance\Enums\OvertimeStatus;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's attendance day, recomputed from punches and the expected schedule (D68) whenever
 * either changes. Closed once the day is over (absences and missing check-outs become final).
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property int|null $branch_id
 * @property CarbonImmutable $date
 * @property string $schedule_source shift|policy|none
 * @property CarbonImmutable|null $scheduled_start
 * @property CarbonImmutable|null $scheduled_end
 * @property int $scheduled_minutes
 * @property CarbonImmutable|null $first_in
 * @property CarbonImmutable|null $last_out
 * @property int $worked_minutes
 * @property int $late_minutes
 * @property int $early_leave_minutes
 * @property int $overtime_minutes
 * @property array<string, array{multiplier: float, minutes: int}>|null $overtime_buckets
 * @property OvertimeStatus $overtime_status
 * @property int $overtime_approved_minutes
 * @property int|null $overtime_request_id
 * @property DayStatus $status
 * @property list<string>|null $flags
 * @property bool $corrected
 * @property CarbonImmutable|null $closed_at
 */
#[Guarded(['id', 'ulid'])]
final class AttendanceDay extends Model
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'scheduled_start' => 'immutable_datetime',
            'scheduled_end' => 'immutable_datetime',
            'first_in' => 'immutable_datetime',
            'last_out' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'overtime_buckets' => 'array',
            'flags' => 'array',
            'status' => DayStatus::class,
            'overtime_status' => OvertimeStatus::class,
            'corrected' => 'boolean',
        ];
    }
}
