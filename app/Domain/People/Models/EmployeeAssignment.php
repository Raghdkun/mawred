<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Effective-dated job placement (D25): exactly one primary at a time (exclusion constraint),
 * plus optional secondary placements for rostering and visibility. Dates are branch-local.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property bool $is_primary
 * @property int $branch_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $manager_employee_id
 * @property int|null $employment_type_id
 * @property int|null $grade_id
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $reason
 * @property bool $is_current
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class EmployeeAssignment extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_primary' => true,
        'department_id' => null,
        'position_id' => null,
        'manager_employee_id' => null,
        'employment_type_id' => null,
        'grade_id' => null,
        'effective_to' => null,
        'reason' => null,
        'is_current' => false,
        'created_by' => null,
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function coversDate(string $date): bool
    {
        return $this->effective_from->toDateString() <= $date
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $date);
    }

    /**
     * Assignments in force on a local date.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function onDate(Builder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_current' => 'boolean',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }
}
