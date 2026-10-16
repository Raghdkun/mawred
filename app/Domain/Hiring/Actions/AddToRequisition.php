<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/** Puts a known candidate (talent pool) on another job's board, with their latest CV. */
final readonly class AddToRequisition
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, Candidate $candidate, JobRequisition $requisition): JobApplication
    {
        $first = PipelineStage::query()->where('job_requisition_id', $requisition->id)->where('kind', StageKind::Applied)->first();

        if ($requisition->status !== RequisitionStatus::Open || ! $first instanceof PipelineStage || $candidate->anonymised_at !== null) {
            throw new DomainRuleViolation('hiring.requisition_not_open', __('This requisition isn’t open, so its board is read-only.'));
        }

        if (JobApplication::query()->where('candidate_id', $candidate->id)->where('job_requisition_id', $requisition->id)->exists()) {
            $message = __(':name is already on this job.', ['name' => $candidate->name]);

            throw new DomainRuleViolation('hiring.already_applied', $message, ['requisition' => [$message]]);
        }

        return $this->db->transaction(function () use ($actor, $candidate, $requisition, $first): JobApplication {
            $now = now();
            $latest = JobApplication::query()->where('candidate_id', $candidate->id)->whereNotNull('cv_upload_id')->latest('applied_at')->first();
            $application = JobApplication::query()->create([
                'candidate_id' => $candidate->id,
                'job_requisition_id' => $requisition->id,
                'pipeline_stage_id' => $first->id,
                'source' => ApplicationSource::TalentPool,
                'form_submission_id' => $latest?->form_submission_id,
                'cv_upload_id' => $latest?->cv_upload_id,
                'applied_at' => $now,
                'stage_entered_at' => $now,
                'last_activity_at' => $now,
            ]);
            $candidate->forceFill(['last_activity_at' => $now])->save();

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Applied,
                'to_stage_id' => $first->id,
                'actor_user_id' => $actor->user?->id,
                'data' => ['source' => ApplicationSource::TalentPool->value],
                'created_at' => $now,
            ]);

            activity('hiring')->causedBy($actor->user)->performedOn($application)->event('applied')
                ->withProperties(['requisition' => $requisition->number, 'source' => ApplicationSource::TalentPool->value])->log('Application received');

            return $application;
        });
    }
}
