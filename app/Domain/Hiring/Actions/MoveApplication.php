<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Events\ApplicationMoved;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\PipelineStage;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Moves a candidate to another stage of the same board (drag on the kanban, or the stage menu).
 * Moving into the "hired" stage marks the application hired.
 */
final readonly class MoveApplication
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, JobApplication $application, PipelineStage $to): JobApplication
    {
        if ($to->job_requisition_id !== $application->job_requisition_id) {
            throw new DomainRuleViolation('hiring.other_board', __('That stage belongs to another job.'));
        }

        if ($application->status !== ApplicationStatus::Active) {
            throw new DomainRuleViolation('hiring.not_active', __('Reopen this application before moving it.'));
        }

        if ($application->requisition()->firstOrFail()->status !== RequisitionStatus::Open) {
            throw new DomainRuleViolation('hiring.requisition_not_open', __('This requisition isn’t open, so its board is read-only.'));
        }

        if ($to->id === $application->pipeline_stage_id) {
            return $application;
        }

        return $this->db->transaction(function () use ($actor, $application, $to): JobApplication {
            $from = $application->pipeline_stage_id;
            $now = now();
            $application->forceFill([
                'pipeline_stage_id' => $to->id,
                'stage_entered_at' => $now,
                'last_activity_at' => $now,
                'status' => $to->kind === StageKind::Hired ? ApplicationStatus::Hired : ApplicationStatus::Active,
            ])->save();
            $application->candidate()->update(['last_activity_at' => $now]);

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Moved,
                'from_stage_id' => $from,
                'to_stage_id' => $to->id,
                'actor_user_id' => $actor->user?->id,
                'created_at' => $now,
            ]);

            event(new ApplicationMoved($application, $from, $to->id, $actor->user?->id));

            return $application;
        });
    }
}
