<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Ends a live enrolment: the learner leaving a course they chose, or a trainer or HR taking
 * someone off one. A request still waiting for approval has its workflow cancelled too.
 */
final readonly class CancelEnrollment
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(Actor $actor, Enrollment $enrollment, ?string $reason = null): Enrollment
    {
        if (! $enrollment->status->isLive()) {
            throw new DomainRuleViolation('training.enrollment_closed', __('This enrolment has already ended.'));
        }

        $instance = $enrollment->workflow_instance_id === null ? null : WorkflowInstance::query()->find($enrollment->workflow_instance_id);

        if ($instance instanceof WorkflowInstance && $instance->isOpen()) {
            $this->engine->cancel($instance, $actor->user, $reason ?? __('The enrolment was cancelled.'));
        }

        $enrollment->forceFill(['status' => EnrollmentStatus::Cancelled, 'cancelled_at' => now(), 'decision_note' => $reason])->save();

        activity('training')->causedBy($actor->user)->performedOn($enrollment)->event('cancelled')->withProperties(['reason' => $reason])->log('Enrolment cancelled');

        return $enrollment;
    }
}
