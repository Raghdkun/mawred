<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Support\ApplicantFields;
use App\Domain\Hiring\Support\CandidateMatcher;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Hiring\Support\Knockouts;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * An application arrives (D81): from the careers site, or typed in by a recruiter. The answers
 * are validated by the form engine like any submission. The same email finds the same candidate
 * (a second application to the same requisition refreshes the first); a known phone on a new
 * email flags a possible duplicate. Knock-out answers flag the application for the recruiter.
 */
final readonly class SubmitApplication
{
    public function __construct(
        private ConnectionInterface $db,
        private SubmitForm $submitForm,
        private HiringSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  array{privacy: bool, talentPool: bool}  $consent  what the applicant agreed to
     */
    public function handle(
        Actor $actor,
        JobRequisition $requisition,
        ?JobPosting $posting,
        array $values,
        ApplicationSource $source,
        array $consent = ['privacy' => false, 'talentPool' => false],
        ?string $sessionHash = null,
        ?string $ip = null,
    ): JobApplication {
        if ($requisition->status !== RequisitionStatus::Open) {
            throw new DomainRuleViolation('hiring.requisition_not_open', __('This job is no longer taking applications.'));
        }

        if ($source === ApplicationSource::Careers && ! $consent['privacy']) {
            $message = __('Please confirm you have read how we use your details.');

            throw new DomainRuleViolation('hiring.consent', $message, ['privacy' => [$message]]);
        }

        $form = $posting?->form()->first() ?? $this->settings->defaultForm();
        $version = $form instanceof Form && $form->published_version_id !== null ? FormVersion::query()->find($form->published_version_id) : null;
        $first = PipelineStage::query()->where('job_requisition_id', $requisition->id)->where('kind', StageKind::Applied)->first();

        if (! $form instanceof Form || ! $version instanceof FormVersion || ! $first instanceof PipelineStage) {
            throw new DomainRuleViolation('hiring.not_ready', __('This job is not ready for applications yet.'));
        }

        $fields = ApplicantFields::of($version->schema);

        return $this->db->transaction(function () use ($actor, $requisition, $posting, $values, $source, $consent, $sessionHash, $ip, $form, $version, $first, $fields): JobApplication {
            $submission = $this->submitForm->handle($actor, $form, $version, $values, SubmissionSource::Public, $sessionHash, $ip);
            $applicant = $fields->read($submission->data);
            $cv = $applicant['cv'] === null ? null : FormUpload::query()->where('form_submission_id', $submission->id)->where('ulid', $applicant['cv'])->value('id');
            $now = now();

            $candidate = CandidateMatcher::sameEmail($applicant['email']);
            $candidate ??= new Candidate([
                'source' => $source,
                'possible_duplicate_of_id' => CandidateMatcher::samePhone($applicant['phone'])?->id,
            ]);
            $candidate->fill([
                'name' => $applicant['name'] !== '' ? $applicant['name'] : ($candidate->name ?? '—'),
                'email' => $applicant['email'] ?? $candidate->email,
                'phone' => $applicant['phone'] ?? $candidate->phone,
                'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
                'last_activity_at' => $now,
            ]);

            if ($consent['privacy']) {
                $candidate->consented_at = $now;
            }

            if ($consent['talentPool']) {
                $candidate->forceFill(['talent_pool' => true, 'talent_pool_consented_at' => $now]);
            }

            $candidate->save();

            $knockouts = $posting instanceof JobPosting ? ($posting->knockouts ?? []) : [];
            $failed = Knockouts::failed($knockouts, $version->schema, $submission->data);
            $application = JobApplication::query()->where('candidate_id', $candidate->id)->where('job_requisition_id', $requisition->id)->lockForUpdate()->first();
            $again = $application instanceof JobApplication;

            $application ??= new JobApplication([
                'candidate_id' => $candidate->id,
                'job_requisition_id' => $requisition->id,
                'pipeline_stage_id' => $first->id,
                'status' => ApplicationStatus::Active,
                'source' => $source,
                'applied_at' => $now,
                'stage_entered_at' => $now,
            ]);
            $application->fill([
                'job_posting_id' => $posting instanceof JobPosting ? $posting->id : $application->job_posting_id,
                'form_submission_id' => $submission->id,
                'cv_upload_id' => $cv ?? $application->cv_upload_id,
                'knocked_out' => $failed !== [],
                'knockout_reasons' => $failed === [] ? null : $failed,
                'last_activity_at' => $now,
            ])->save();

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Applied,
                'to_stage_id' => $again ? null : $first->id,
                'actor_user_id' => $actor->user?->id,
                'data' => array_filter(['source' => $source->value, 'again' => $again ?: null, 'posting' => $posting?->ulid]),
                'created_at' => $now,
            ]);

            activity('hiring')->causedBy($actor->user)->performedOn($application)->event($again ? 'reapplied' : 'applied')
                ->withProperties(['requisition' => $requisition->number, 'source' => $source->value, 'knocked_out' => $failed !== []])->log('Application received');

            return $application;
        });
    }
}
