<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Audit\Contracts\MasksAuditedAttributes;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Ids\HasPublicUlid;
use App\Support\Money\EncryptedMoneyCast;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job offer (D83, D91, D95): the terms, the letter in the candidate's language, its approval
 * workflow, and the candidate's typed name and drawn signature once accepted. Pay and the letter
 * are encrypted at rest.
 *
 * @property int $id
 * @property string $ulid
 * @property int $job_application_id
 * @property OfferStatus $status
 * @property string $job_title
 * @property CarbonImmutable $start_on
 * @property Money|null $salary
 * @property int $branch_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $employment_type_id
 * @property int|null $manager_employee_id
 * @property string $locale
 * @property string $letter
 * @property CarbonImmutable|null $expires_on
 * @property int|null $workflow_instance_id
 * @property int|null $created_by_user_id
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $responded_at
 * @property string|null $signed_name
 * @property string|null $signature_path
 * @property string|null $signed_ip_hash
 * @property string|null $signed_pdf_path
 * @property string|null $decline_reason
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class Offer extends Model implements MasksAuditedAttributes
{
    use Auditable;
    use HasPublicUlid;

    /** Private files: the drawn signature and the signed letter. */
    public const string DISK = 'local';

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
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
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function auditMaskedAttributes(): array
    {
        return ['salary_minor', 'letter', 'signed_ip_hash'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'salary' => EncryptedMoneyCast::class,
            'letter' => 'encrypted',
            'start_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'sent_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
