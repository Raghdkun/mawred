<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Enums\RejectionReason;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\CandidateTemplates;
use App\Domain\Hiring\Support\HiringSettings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Takes a candidate off the board with a reason; they stay in the talent pool search. With
 * `$notify`, the thank-you message from Hiring settings goes to the candidate (D93).
 */
final readonly class RejectApplication
{
    public function __construct(
        private ConnectionInterface $db,
        private HiringSettings $settings,
        private CandidateTemplates $templates,
        private SendCandidateMessage $messages,
    ) {}

    public function handle(Actor $actor, JobApplication $application, RejectionReason $reason, ?string $note = null, bool $notify = false): JobApplication
    {
        if ($application->status !== ApplicationStatus::Active) {
            throw new DomainRuleViolation('hiring.not_active', __('Only active applications can be rejected.'));
        }

        $application = $this->db->transaction(function () use ($actor, $application, $reason, $note): JobApplication {
            $now = now();
            $application->forceFill([
                'status' => ApplicationStatus::Rejected,
                'rejection_reason' => $reason,
                'rejected_at' => $now,
                'last_activity_at' => $now,
            ])->save();
            $application->candidate()->update(['last_activity_at' => $now]);

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Rejected,
                'from_stage_id' => $application->pipeline_stage_id,
                'actor_user_id' => $actor->user?->id,
                'body' => $note === null || trim($note) === '' ? null : trim($note),
                'data' => ['reason' => $reason->value],
                'created_at' => $now,
            ]);

            activity('hiring')->causedBy($actor->user)->performedOn($application)->event('rejected')
                ->withProperties(['reason' => $reason->value])->log('Application rejected');

            return $application;
        });

        $template = $notify ? $this->settings->rejectionMessage() : null;
        $candidate = $application->candidate()->first();

        if ($template !== null && $candidate instanceof Candidate && $candidate->anonymised_at === null && ($candidate->email !== null || $candidate->phone !== null)) {
            try {
                $this->messages->handle(
                    $actor,
                    $candidate,
                    $application,
                    $candidate->email !== null ? MessageChannel::Email : MessageChannel::WhatsApp,
                    $this->templates->render($template['subject'], $candidate, $application),
                    $this->templates->render($template['body'], $candidate, $application),
                    MessagePurpose::Rejection,
                );
            } catch (DomainRuleViolation) {
                // The rejection stands; the message log shows what failed.
            }
        }

        return $application;
    }
}
