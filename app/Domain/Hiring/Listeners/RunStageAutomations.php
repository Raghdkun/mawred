<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Listeners;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\ScheduleInterview;
use App\Domain\Hiring\Actions\SendCandidateMessage;
use App\Domain\Hiring\Data\InterviewInputData;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Events\ApplicationMoved;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Support\CandidateTemplates;
use App\Domain\Hiring\Support\Texts;
use App\Domain\Organization\Models\Branch;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Stage automations (D93), after the move is committed: the stage's message to the candidate and,
 * on interview stages, a self-booking invite. A failure is logged on the candidate (the message
 * log or the history) and never undoes the move.
 */
final readonly class RunStageAutomations
{
    public function __construct(
        private CandidateTemplates $templates,
        private SendCandidateMessage $messages,
        private ScheduleInterview $interviews,
    ) {}

    public function handle(ApplicationMoved $event): void
    {
        $application = JobApplication::query()->with(['candidate', 'requisition'])->find($event->application->id);
        $stage = PipelineStage::query()->find($event->toStageId);
        $automation = $stage instanceof PipelineStage ? ($stage->automation ?? []) : [];
        $candidate = $application?->candidate;

        if (! $application instanceof JobApplication || ! $candidate instanceof Candidate || $application->status !== ApplicationStatus::Active || $candidate->anonymised_at !== null) {
            return;
        }

        $actor = $event->actorUserId !== null && ($user = User::query()->find($event->actorUserId)) instanceof User
            ? Actor::user($user)
            : Actor::system('hiring-automation');

        $message = $automation['message'] ?? null;

        if (is_array($message) && $message['enabled'] && ($candidate->email !== null || $candidate->phone !== null)) {
            try {
                $this->messages->handle(
                    $actor,
                    $candidate,
                    $application,
                    $candidate->email !== null ? MessageChannel::Email : MessageChannel::WhatsApp,
                    $this->templates->render($message['subject'], $candidate, $application),
                    $this->templates->render($message['body'], $candidate, $application),
                    MessagePurpose::Stage,
                );
            } catch (DomainRuleViolation) {
                // Logged by the messenger where it could be; nothing to undo.
            }
        }

        $booking = $automation['booking'] ?? null;

        if (is_array($booking) && $booking['enabled'] && ! $application->interviews()->whereIn('status', ['awaiting', 'scheduled'])->exists()) {
            $timezone = Branch::query()->whereKey($application->requisition?->branch_id)->value('timezone');

            try {
                $this->interviews->handle($actor->user instanceof User ? $actor : Actor::user(User::query()->where('ulid', $booking['panel'][0] ?? '')->firstOrFail()), $application, InterviewInputData::from([
                    'title' => $booking['title'] !== '' ? $booking['title'] : (Texts::one($stage, 'name') ?? 'Interview'),
                    'durationMinutes' => $booking['duration'],
                    'panel' => $booking['panel'],
                    'timezone' => is_string($timezone) ? $timezone : config('app.timezone'),
                ]));
            } catch (DomainRuleViolation) {
                // No contact details or the panel changed; the recruiter plans it by hand.
            }
        }
    }
}
