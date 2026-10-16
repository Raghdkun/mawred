<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Support\AnswerLabels;
use App\Domain\Hiring\Actions\AddToRequisition;
use App\Domain\Hiring\Actions\DismissDuplicate;
use App\Domain\Hiring\Actions\MergeCandidates;
use App\Domain\Hiring\Actions\SetTalentPool;
use App\Domain\Hiring\Actions\TagCandidate;
use App\Domain\Hiring\Data\ApplicationDetailData;
use App\Domain\Hiring\Data\ApplicationEventData;
use App\Domain\Hiring\Data\CandidateProfileData;
use App\Domain\Hiring\Data\CandidateRowData;
use App\Domain\Hiring\Data\StageData;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\HiringTag;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Queries\ApplicationsQuery;
use App\Domain\Hiring\Queries\CandidatesQuery;
use App\Domain\Hiring\Support\Texts;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidates (handoff 30): the talent pool search, a candidate's profile with each application
 * (CV preview, answers, history), tags, the talent pool flag, and duplicate review.
 */
final class CandidatesController extends Controller
{
    public function index(Request $request, CandidatesQuery $query, #[CurrentUser] User $user): Response
    {
        Gate::authorize('hiring.view');
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:40'],
            'view' => ['nullable', 'in:all,pool,duplicates'],
        ]);
        $view = $request->string('view', 'all')->toString();
        $tag = $request->filled('tag') ? HiringTag::query()->whereRaw('lower(name) = ?', [mb_strtolower($request->string('tag')->toString())])->first() : null;

        $page = $query->search($request->string('search')->toString())
            ->taggedWith($request->filled('tag') ? ($tag->id ?? 0) : null)
            ->poolOnly($view === 'pool')
            ->duplicatesOnly($view === 'duplicates')
            ->visibleTo($user)
            ->with(['tags', 'applications.requisition', 'applications.stage'])
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('hiring/candidates', [
            'candidates' => [
                'data' => array_values(array_map(CandidateRowData::fromCandidate(...), $page->items())),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ],
            'filters' => ['search' => $request->string('search')->toString(), 'tag' => $request->string('tag')->toString(), 'view' => $view],
            'tags' => HiringTag::query()->orderBy('name')->limit(200)->pluck('name'),
        ]);
    }

    public function show(Request $request, Candidate $candidate, ApplicationsQuery $applications, #[CurrentUser] User $user): Response
    {
        abort_unless(Gate::allows('hiring.viewCandidate', $candidate), 404);
        $request->validate(['application' => ['nullable', 'string', 'size:26']]);
        $candidate->load(['tags', 'possibleDuplicateOf']);
        $locale = app()->getLocale();

        $details = $applications->forCandidate($candidate->id)->visibleTo($user)
            ->with(['requisition.stages', 'stage', 'cv', 'submission.version', 'events.actor', 'events.fromStage', 'events.toStage'])
            ->get()
            ->sortByDesc('applied_at')
            ->values()
            ->map(function (JobApplication $application) use ($locale, $user): ApplicationDetailData {
                /** @var JobRequisition $requisition */
                $requisition = $application->requisition;
                $submission = $application->submission;
                $schema = $submission instanceof FormSubmission ? $submission->pinnedVersion()->schema : null;
                $answers = $submission instanceof FormSubmission ? $submission->data : [];
                $cv = $application->cv;

                return new ApplicationDetailData(
                    $application->ulid,
                    ['id' => $requisition->ulid, 'number' => $requisition->number, 'title' => Texts::one($requisition, 'title', $locale) ?? $requisition->number, 'open' => $requisition->status->isActive()],
                    $application->stage->ulid ?? '',
                    array_values($requisition->stages->map(StageData::fromStage(...))->all()),
                    $application->status->value,
                    $application->source->value,
                    $application->applied_at->toIso8601String(),
                    $application->stage_entered_at->toIso8601String(),
                    array_map(fn (array $k): string => $k['label'][$locale === 'ar' ? 'ar' : 'en'], $application->knockout_reasons ?? []),
                    $application->rejection_reason?->value,
                    $schema,
                    $answers,
                    $schema === null ? [] : AnswerLabels::for($schema, $answers, $locale),
                    $cv instanceof FormUpload ? ['url' => route('hiring.applications.files', [$application, $cv]), 'name' => $cv->original_name, 'mime' => $cv->mime] : null,
                    array_values($application->events->map(ApplicationEventData::fromEvent(...))->all()),
                    ['update' => Gate::forUser($user)->allows('hiring.updateApplication', $application)],
                );
            });

        activity('hiring')->causedBy($user)->performedOn($candidate)->event('viewed')->log('Candidate profile viewed');

        return Inertia::render('hiring/candidate', [
            'candidate' => CandidateProfileData::fromCandidate($candidate),
            'applications' => array_values($details->all()),
            'selected' => $request->string('application')->lower()->toString() ?: null,
            'can' => ['update' => Gate::allows('hiring.updateCandidate', $candidate)],
            'tags' => HiringTag::query()->orderBy('name')->limit(200)->pluck('name'),
        ]);
    }

    public function file(JobApplication $application, FormUpload $upload, #[CurrentUser] User $user): StreamedResponse
    {
        abort_unless(Gate::allows('hiring.viewApplication', $application), 404);
        abort_unless($upload->id === $application->cv_upload_id || ($application->form_submission_id !== null && $upload->form_submission_id === $application->form_submission_id), 404);

        activity('hiring')->causedBy($user)->performedOn($application)->event('file.viewed')
            ->withProperties(['file' => $upload->ulid])->log('Candidate file viewed');

        // PDFs open in the CV viewer; anything else downloads.
        $disposition = $upload->mime === 'application/pdf' ? 'inline' : 'attachment';

        return Storage::disk(FormUpload::DISK)->response($upload->path, $upload->original_name, [
            'Content-Type' => $upload->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }

    public function tags(Request $request, Candidate $candidate, TagCandidate $tag, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($candidate);
        $request->validate(['tags' => ['present', 'array', 'max:20'], 'tags.*' => ['string', 'max:40']]);

        /** @var list<string> $names */
        $names = $request->input('tags', []);
        $tag->handle(Actor::user($user), $candidate, $names);

        return back();
    }

    public function pool(Request $request, Candidate $candidate, SetTalentPool $pool, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($candidate);
        $request->validate(['inPool' => ['required', 'boolean']]);
        $pool->handle(Actor::user($user), $candidate, $request->boolean('inPool'));

        return back();
    }

    public function merge(Candidate $candidate, MergeCandidates $merge, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($candidate);
        $keep = $candidate->possibleDuplicateOf()->first();
        abort_unless($keep instanceof Candidate && Gate::allows('hiring.updateCandidate', $keep), 404);
        $merge->handle(Actor::user($user), $keep, $candidate);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Candidates merged.')]);

        return to_route('hiring.candidates.show', $keep);
    }

    public function notDuplicate(Candidate $candidate, DismissDuplicate $dismiss, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($candidate);
        $dismiss->handle(Actor::user($user), $candidate);

        return back();
    }

    public function addToRequisition(Request $request, Candidate $candidate, AddToRequisition $add, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('hiring.viewCandidate', $candidate), 404);
        $request->validate(['requisition' => ['required', 'string', 'size:26']]);
        $requisition = JobRequisition::query()->where('ulid', $request->string('requisition')->lower()->toString())->firstOrFail();
        abort_unless(Gate::allows('hiring.viewRequisition', $requisition), 404);
        abort_unless(Gate::allows('hiring.updateRequisition', $requisition), 403);
        $application = $add->handle(Actor::user($user), $candidate, $requisition);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to :job.', ['name' => $candidate->name, 'job' => Texts::one($requisition, 'title') ?? $requisition->number])]);

        return to_route('hiring.candidates.show', [$candidate, 'application' => $application->ulid]);
    }

    private function authorizeUpdate(Candidate $candidate): void
    {
        abort_unless(Gate::allows('hiring.viewCandidate', $candidate), 404);
        abort_unless(Gate::allows('hiring.updateCandidate', $candidate), 403);
    }
}
