<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Hiring\Enums\RequisitionReason;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\Hiring\JobRequisitionFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * An approved need to hire (D84). Its hiring team and pipeline live with it; postings advertise
 * it and applications move through its stages.
 *
 * @property int $id
 * @property string $ulid
 * @property string $number
 * @property array<string, string>|string $title
 * @property int $branch_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $employment_type_id
 * @property int $headcount
 * @property RequisitionReason $reason
 * @property int|null $hiring_manager_employee_id
 * @property int|null $requested_by_user_id
 * @property int|null $request_id
 * @property int|null $salary_min_minor
 * @property int|null $salary_max_minor
 * @property string|null $currency
 * @property CarbonImmutable|null $target_start_on
 * @property string|null $justification
 * @property RequisitionStatus $status
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
#[UseFactory(JobRequisitionFactory::class)]
final class JobRequisition extends Model
{
    use Auditable;

    /** @use HasFactory<JobRequisitionFactory> */
    use HasFactory;

    use HasPublicUlid;
    use HasTranslations;

    /** The seeded request type that carries a requisition's approval (D84). */
    public const string REQUEST_TYPE = 'JOB_REQUISITION';

    /** @var list<string> */
    public array $translatable = ['title'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending', 'reason' => 'new', 'headcount' => 1];

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
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<EmployeeRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    /**
     * @return HasMany<RequisitionMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(RequisitionMember::class);
    }

    /**
     * @return HasMany<PipelineStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position');
    }

    /**
     * @return HasMany<ScorecardCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(ScorecardCriterion::class)->orderBy('position');
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => RequisitionReason::class,
            'status' => RequisitionStatus::class,
            'headcount' => 'integer',
            'salary_min_minor' => 'integer',
            'salary_max_minor' => 'integer',
            'target_start_on' => 'immutable_date',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
