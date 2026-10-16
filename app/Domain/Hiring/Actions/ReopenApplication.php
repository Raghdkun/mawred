<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\JobApplication;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/** Puts a rejected or withdrawn application back on the board, in the stage it left. */
final readonly class ReopenApplication
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, JobApplication $application): JobApplication
    {
        if (! in_array($application->status, [ApplicationStatus::Rejected, ApplicationStatus::Withdrawn], true)) {
            throw new DomainRuleViolation('hiring.not_closed', __('This application is already on the board.'));
        }

        return $this->db->transaction(function () use ($actor, $application): JobApplication {
            $now = now();
            $application->forceFill([
                'status' => ApplicationStatus::Active,
                'rejection_reason' => null,
                'rejected_at' => null,
                'stage_entered_at' => $now,
                'last_activity_at' => $now,
            ])->save();

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Reopened,
                'to_stage_id' => $application->pipeline_stage_id,
                'actor_user_id' => $actor->user?->id,
                'created_at' => $now,
            ]);

            activity('hiring')->causedBy($actor->user)->performedOn($application)->event('reopened')->log('Application reopened');

            return $application;
        });
    }
}
