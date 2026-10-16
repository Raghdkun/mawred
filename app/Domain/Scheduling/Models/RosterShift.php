<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One shift on the roster, on a branch-local date. Without an employee it is an open shift that
 * eligible people can claim (D73). Draft until published; editing a published shift drafts it
 * again, so people only ever see what was published.
 *
 * @property int $id
 * @property string $ulid
 * @property int $branch_id
 * @property int|null $employee_id
 * @property int|null $shift_template_id
 * @property int|null $position_id
 * @property CarbonImmutable $date
 * @property string $starts_at H:i:s
 * @property string $ends_at H:i:s
 * @property int $break_minutes
 * @property ShiftStatus $status
 * @property CarbonImmutable|null $published_at
 * @property bool $auto_assign
 * @property string|null $note
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class RosterShift extends Model
{
    use Auditable;
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
     * @return BelongsTo<ShiftTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class)->withTrashed();
    }

    /**
     * @return HasMany<OpenShiftClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(OpenShiftClaim::class);
    }

    public function isOpen(): bool
    {
        return $this->employee_id === null;
    }

    /**
     * Minutes from the start of its date: [start, end), end past 1440 for overnight shifts.
     *
     * @return array{0: int, 1: int}
     */
    public function span(): array
    {
        $start = self::minutes($this->starts_at);
        $end = self::minutes($this->ends_at);

        return [$start, $end <= $start ? $end + 1440 : $end];
    }

    public function paidMinutes(): int
    {
        $span = $this->span();

        return max(0, $span[1] - $span[0] - $this->break_minutes);
    }

    public static function minutes(string $time): int
    {
        $parts = explode(':', $time);

        return (int) $parts[0] * 60 + (int) ($parts[1] ?? 0);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'date' => 'immutable_date',
            'published_at' => 'immutable_datetime',
            'break_minutes' => 'integer',
            'auto_assign' => 'boolean',
        ];
    }
}
