<?php

declare(strict_types=1);

namespace App\Domain\Requests\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request someone made (handoff 11–12): its answers (a form submission) and the workflow
 * instance deciding it. Scoped by the requester's placement at submission.
 *
 * @property int $id
 * @property string $ulid
 * @property string $number
 * @property int $request_type_id
 * @property int $requester_employee_id
 * @property int|null $requester_user_id
 * @property int|null $branch_id
 * @property int|null $department_id
 * @property int $form_submission_id
 * @property int|null $workflow_instance_id
 * @property RequestStatus $status
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
#[Table(name: 'requests')]
final class EmployeeRequest extends Model
{
    use Auditable;
    use HasPublicUlid;

    /**
     * @return BelongsTo<RequestType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /**
     * @return HasMany<RequestComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(RequestComment::class, 'request_id');
    }

    public function requestType(): RequestType
    {
        return $this->type ?? RequestType::withTrashed()->findOrFail($this->request_type_id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => RequestStatus::class, 'submitted_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }
}
