<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\CandidateMessage;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Models\Scorecard;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a candidate's personal data (D86, D96): name, contact details, answers, files, messages,
 * notes, feedback text and offer letters. Stage history, ratings and dates stay for funnel
 * reports. Open applications are withdrawn first. Someone who was hired is an employee now, so
 * their record is never anonymised here.
 */
final readonly class AnonymiseCandidate
{
    public const string NAME = 'Anonymised candidate';

    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  'retention'|'request'|'hr'  $reason
     */
    public function handle(Actor $actor, Candidate $candidate, string $reason): Candidate
    {
        if ($candidate->anonymised_at !== null) {
            return $candidate;
        }

        $applicationIds = JobApplication::query()->where('candidate_id', $candidate->id)->pluck('id')->all();

        if (JobApplication::query()->whereKey($applicationIds)->where('status', ApplicationStatus::Hired)->exists()
            || Offer::query()->whereIn('job_application_id', $applicationIds)->where('status', OfferStatus::Accepted)->exists()) {
            throw new DomainRuleViolation('hiring.hired_candidate', __('This candidate was hired; their details live on their employee record now.'));
        }

        $files = [];

        $this->db->transaction(function () use ($actor, $candidate, $reason, $applicationIds, &$files): void {
            JobApplication::query()->whereKey($applicationIds)->where('status', ApplicationStatus::Active)
                ->update(['status' => ApplicationStatus::Withdrawn, 'updated_at' => now()]);

            $submissionIds = JobApplication::query()->whereKey($applicationIds)->whereNotNull('form_submission_id')->pluck('form_submission_id')->all();

            foreach (FormUpload::query()->whereIn('form_submission_id', $submissionIds)->get() as $upload) {
                $files[] = [FormUpload::DISK, $upload->path];
                $upload->delete();
            }

            foreach (FormSubmission::query()->whereKey($submissionIds)->get() as $submission) {
                $submission->forceFill(['data' => []])->save();
            }

            ApplicationEvent::query()->whereIn('job_application_id', $applicationIds)->update(['body' => null]);

            $interviews = Interview::query()->whereIn('job_application_id', $applicationIds)->get();

            foreach ($interviews as $interview) {
                // The calendar entry carried the candidate's name.
                CalendarEvent::query()->whereKey($interview->calendar_event_id)->update(['title' => $interview->title, 'description' => null]);
            }

            foreach (Scorecard::query()->whereIn('interview_id', $interviews->modelKeys())->get() as $card) {
                $card->forceFill([
                    'comment' => null,
                    'ratings' => array_map(fn (array $r): array => ['rating' => $r['rating'], 'note' => null], $card->ratings),
                ])->save();
            }

            foreach (Offer::query()->whereIn('job_application_id', $applicationIds)->get() as $offer) {
                foreach ([$offer->signature_path, $offer->signed_pdf_path] as $path) {
                    if ($path !== null) {
                        $files[] = [Offer::DISK, $path];
                    }
                }

                $offer->forceFill(['letter' => '', 'signed_name' => null, 'signature_path' => null, 'signed_pdf_path' => null, 'signed_ip_hash' => null, 'decline_reason' => null])->save();
            }

            CandidateMessage::query()->where('candidate_id', $candidate->id)->delete();
            $candidate->tags()->detach();

            $candidate->forceFill([
                'name' => self::NAME,
                'email' => null,
                'phone' => null,
                'location' => null,
                'talent_pool' => false,
                'possible_duplicate_of_id' => null,
                'anonymised_at' => now(),
            ])->save();
            Candidate::query()->where('possible_duplicate_of_id', $candidate->id)->update(['possible_duplicate_of_id' => null]);

            foreach ($applicationIds as $id) {
                ApplicationEvent::query()->create([
                    'job_application_id' => $id,
                    'kind' => ApplicationEventKind::Note,
                    'actor_user_id' => $actor->user?->id,
                    'data' => ['anonymised' => $reason],
                    'created_at' => now(),
                ]);
            }

            activity('hiring')->causedBy($actor->user)->performedOn($candidate)->event('anonymised')
                ->withProperties(['reason' => $reason, 'applications' => count($applicationIds)])->log('Candidate anonymised');
        });

        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $candidate;
    }
}
