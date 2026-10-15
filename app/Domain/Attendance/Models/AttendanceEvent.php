<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\GeofenceResult;
use App\Domain\Attendance\Enums\PunchKind;
use App\Domain\Attendance\Enums\PunchSource;
use App\Domain\People\Models\Employee;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A punch (append-only, monthly partitions, ADR-0018). `occurred_at` is when it happened (the
 * device's time for offline punches within the drift budget), `received_at` when it arrived;
 * `local_date` is the attendance day it counts for. Corrections void punches, never edit them.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property int|null $branch_id
 * @property PunchKind $kind
 * @property PunchSource $source
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable $local_date
 * @property int|null $drift_seconds
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $accuracy_m
 * @property GeofenceResult $geofence
 * @property bool $flagged
 * @property string|null $reason
 * @property int|null $kiosk_id
 * @property CarbonImmutable|null $voided_at
 * @property int|null $actor_user_id
 */
#[Guarded(['id', 'ulid'])]
final class AttendanceEvent extends Model
{
    use HasPublicUlid;

    public const null UPDATED_AT = null;

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
        return [
            'kind' => PunchKind::class,
            'source' => PunchSource::class,
            'geofence' => GeofenceResult::class,
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'local_date' => 'immutable_date',
            'flagged' => 'boolean',
            'drift_seconds' => 'integer',
            'accuracy_m' => 'integer',
        ];
    }
}
