<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Support\AnswerLabels;
use App\Domain\Hiring\Data\ApplicationDetailData;
use App\Domain\Hiring\Data\ApplicationEventData;
use App\Domain\Hiring\Data\InterviewData;
use App\Domain\Hiring\Data\OfferData;
use App\Domain\Hiring\Data\ScorecardData;
use App\Domain\Hiring\Data\StageData;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Models\Scorecard;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Domain\Hiring\Queries\ApplicationsQuery;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the candidate profile (handoff 30): every application the viewer may see, through the
 * hiring scope or a seat on one of its interview panels (D92). Panelists get the CV, answers and
 * their own interviews; the hiring team gets history, offers and the hire too.
 */
final readonly class CandidateProfile
{
    public function __construct(private ApplicationsQuery $applications, private PanelAccess $panels) {}

    /**
     * @return list<ApplicationDetailData>
     */
    public function for(Candidate $candidate, User $viewer): array
    {
        $scoped = $this->applications->forCandidate($candidate->id)->visibleTo($viewer)->pluck('job_applications.id')->all();
        $onPanel = $this->panels->applicationIds($viewer)->pluck('i.job_application_id')->all();
        $locale = app()->getLocale();

        return array_values(JobApplication::query()
            ->where('candidate_id', $candidate->id)
            ->whereIn('id', [...$scoped, ...$onPanel])
            ->with([
                'requisition.stages', 'requisition.criteria', 'requisition.branch', 'requisition.department', 'requisition.position', 'requisition.hiringManager', 'stage', 'cv', 'submission.version',
                'history.actor', 'history.fromStage', 'history.toStage',
                'interviews.panel', 'interviews.scorecards.user',
                'offers.branch', 'offers.department', 'offers.position', 'offers.employmentType', 'offers.manager',
            ])
            ->get()
            ->sortByDesc('applied_at')
            ->values()
            ->map(function (JobApplication $application) use ($viewer, $locale, $scoped): ApplicationDetailData {
                /** @var JobRequisition $requisition */
                $requisition = $application->requisition;
                $see = in_array($application->id, $scoped, true);
                $update = $see && Gate::forUser($viewer)->allows('hiring.updateApplication', $application);
                $submission = $application->submission;
                $schema = $submission instanceof FormSubmission ? $submission->pinnedVersion()->schema : null;
                $answers = $submission instanceof FormSubmission ? $submission->data : [];
                $cv = $application->cv;
                $interviews = $application->interviews->filter(fn (Interview $i): bool => $see || $i->panel->contains('id', $viewer->id));
                $accepted = $application->offers->first(fn (Offer $o): bool => $o->status === OfferStatus::Accepted);

                return new ApplicationDetailData(
                    id: $application->ulid,
                    job: ['id' => $requisition->ulid, 'number' => $requisition->number, 'title' => Texts::one($requisition, 'title', $locale) ?? $requisition->number, 'open' => $requisition->status->isActive()],
                    stageId: $application->stage->ulid ?? '',
                    stages: array_values($requisition->stages->map(StageData::fromStage(...))->all()),
                    status: $application->status->value,
                    source: $application->source->value,
                    appliedAt: $application->applied_at->toIso8601String(),
                    stageEnteredAt: $application->stage_entered_at->toIso8601String(),
                    knockouts: array_map(fn (array $k): string => $k['label'][$locale === 'ar' ? 'ar' : 'en'], $application->knockout_reasons ?? []),
                    rejectionReason: $application->rejection_reason?->value,
                    schema: $schema,
                    answers: $answers,
                    display: $schema === null ? [] : AnswerLabels::for($schema, $answers, $locale),
                    cv: $cv instanceof FormUpload ? ['url' => route('hiring.applications.files', [$application, $cv]), 'name' => $cv->original_name, 'mime' => $cv->mime] : null,
                    events: $see ? array_values($application->history->map(ApplicationEventData::fromEvent(...))->all()) : [],
                    can: ['update' => $update, 'see' => $see],
                    interviews: array_values($interviews->map(fn (Interview $i): InterviewData => $this->interview($i, $viewer, $update))->all()),
                    criteria: array_values($requisition->criteria->map(fn (ScorecardCriterion $c): array => ['id' => $c->ulid, 'name' => Texts::one($c, 'name', $locale) ?? ''])->all()),
                    offers: $see ? array_values($application->offers->map(OfferData::fromOffer(...))->all()) : [],
                    hire: $update && $accepted instanceof Offer && $application->status === ApplicationStatus::Active ? [
                        'nameEn' => $application->candidate->name ?? '',
                        'hireDate' => $accepted->start_on->toDateString(),
                        'branchId' => $accepted->branch->ulid ?? '',
                        'branch' => Texts::one($accepted->branch, 'name', $locale),
                        'departmentId' => $accepted->department?->ulid,
                        'department' => Texts::one($accepted->department, 'name', $locale),
                        'positionId' => $accepted->position?->ulid,
                        'position' => Texts::one($accepted->position, 'title', $locale),
                        'managerId' => $accepted->manager?->ulid,
                        'manager' => $accepted->manager?->displayName($locale),
                        'employmentTypeId' => $accepted->employmentType?->ulid,
                    ] : null,
                    employeeId: $see ? $application->employee?->ulid : null,
                    score: $application->score === null ? null : (float) $application->score,
                    offerDefaults: $update ? [
                        'jobTitle' => Texts::one($requisition, 'title', $locale) ?? '',
                        'branchId' => $requisition->branch->ulid ?? '',
                        'branch' => Texts::one($requisition->branch, 'name', $locale),
                        'departmentId' => $requisition->department?->ulid,
                        'department' => Texts::one($requisition->department, 'name', $locale),
                        'positionId' => $requisition->position?->ulid,
                        'position' => Texts::one($requisition->position, 'title', $locale),
                        'managerId' => $requisition->hiringManager?->ulid,
                        'manager' => $requisition->hiringManager?->displayName($locale),
                        'currency' => $requisition->currency ?? ($requisition->branch->default_currency ?? 'SAR'),
                        'salaryMinor' => $requisition->salary_min_minor,
                    ] : null,
                );
            })
            ->all());
    }

    private function interview(Interview $interview, User $viewer, bool $manage): InterviewData
    {
        $mine = $interview->scorecards->first(fn (Scorecard $s): bool => $s->user_id === $viewer->id);
        $onPanel = $interview->panel->contains('id', $viewer->id);
        $readOthers = $manage || ($mine instanceof Scorecard && $mine->submitted_at !== null);
        $submitted = $interview->scorecards->filter(fn (Scorecard $s): bool => $s->submitted_at !== null);

        return new InterviewData(
            id: $interview->ulid,
            title: $interview->title,
            status: $interview->status->value,
            mode: $interview->mode->value,
            location: $interview->location,
            durationMinutes: $interview->duration_minutes,
            timezone: $interview->timezone,
            startsAt: $interview->starts_at?->toIso8601String(),
            endsAt: $interview->ends_at?->toIso8601String(),
            bookingUrl: $manage && $interview->status === InterviewStatus::AwaitingBooking ? CandidateLinks::booking($interview) : null,
            panel: array_values($interview->panel->map(fn (User $u): array => ['id' => $u->ulid, 'name' => $u->name])->all()),
            mine: $mine instanceof Scorecard ? ScorecardData::fromScorecard($mine) : null,
            scorecards: $readOthers ? array_values($submitted->reject(fn (Scorecard $s): bool => $s->user_id === $viewer->id)->map(ScorecardData::fromScorecard(...))->all()) : [],
            submitted: $submitted->count(),
            can: ['score' => $onPanel && $interview->status !== InterviewStatus::Cancelled, 'manage' => $manage, 'readOthers' => $readOthers],
        );
    }
}
