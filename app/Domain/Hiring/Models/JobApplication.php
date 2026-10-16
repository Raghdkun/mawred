<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\RejectionReason;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A candidate in one requisition's pipeline: one per candidate and requisition. The answers live
 * on the form submission; the CV is the upload the candidate attached.
 *
 * @property int $id
 * @property string $ulid
 * @property int $candidate_id
 * @property int $job_requisition_id
 * @property int|null $job_posting_id
 * @property int $pipeline_stage_id
 * @property ApplicationStatus $status
 * @property ApplicationSource $source
 * @property int|null $form_submission_id
 * @property int|null $cv_upload_id
 * @property bool $knocked_out
 * @property list<array{field: string, label: array{en: string, ar: string}}>|null $knockout_reasons
 * @property RejectionReason|null $rejection_reason
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable $applied_at
 * @property CarbonImmutable $stage_entered_at
 * @property CarbonImmutable $last_activity_at
 */
#[Guarded(['id', 'ulid'])]
final class JobApplication extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'knocked_out' => false];

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<JobRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /**
     * @return BelongsTo<FormUpload, $this>
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(FormUpload::class, 'cv_upload_id');
    }

    /**
     * @return HasMany<ApplicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['last_activity_at', 'stage_entered_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => ApplicationSource::class,
            'rejection_reason' => RejectionReason::class,
            'knocked_out' => 'boolean',
            'knockout_reasons' => 'array',
            'rejected_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
            'stage_entered_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
        ];
    }
}
