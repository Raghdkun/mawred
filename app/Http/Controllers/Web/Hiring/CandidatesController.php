<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Hiring\Actions\AddToRequisition;
use App\Domain\Hiring\Actions\AnonymiseCandidate;
use App\Domain\Hiring\Actions\DismissDuplicate;
use App\Domain\Hiring\Actions\MergeCandidates;
use App\Domain\Hiring\Actions\SendCandidateMessage;
use App\Domain\Hiring\Actions\SetTalentPool;
use App\Domain\Hiring\Actions\TagCandidate;
use App\Domain\Hiring\Data\ApplicationDetailData;
use App\Domain\Hiring\Data\CandidateMessageData;
use App\Domain\Hiring\Data\CandidateProfileData;
use App\Domain\Hiring\Data\CandidateRowData;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Enums\MessageStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\HiringTag;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Queries\CandidatesQuery;
use App\Domain\Hiring\Support\CandidateProfile;
use App\Domain\Hiring\Support\Texts;
use App\Domain\Organization\Models\EmploymentType;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            ->withQueryString()
            ->through(fn (Candidate $c): CandidateRowData => CandidateRowData::fromCandidate($c));

        return Inertia::render('hiring/candidates', [
            'candidates' => $page,
            'filters' => ['search' => $request->string('search')->toString(), 'tag' => $request->string('tag')->toString(), 'view' => $view],
            'tags' => HiringTag::query()->orderBy('name')->limit(200)->pluck('name'),
        ]);
    }

    public function show(Request $request, Candidate $candidate, CandidateProfile $profile, #[CurrentUser] User $user): Response
    {
        abort_unless(Gate::allows('hiring.viewCandidate', $candidate), 404);
        $request->validate(['application' => ['nullable', 'string', 'size:26']]);
        $candidate->load(['tags', 'possibleDuplicateOf']);
        $applications = $profile->for($candidate, $user);
        $seesAll = collect($applications)->contains(fn (ApplicationDetailData $a): bool => $a->can['see']);

        activity('hiring')->causedBy($user)->performedOn($candidate)->event('viewed')->log('Candidate profile viewed');

        return Inertia::render('hiring/candidate', [
            'candidate' => CandidateProfileData::fromCandidate($candidate),
            'applications' => $applications,
            'selected' => $request->string('application')->lower()->toString() ?: null,
            'messages' => $seesAll ? array_values($candidate->messages()->with('sentBy')->limit(200)->get()->map(CandidateMessageData::fromMessage(...))->all()) : null,
            'can' => [
                'update' => Gate::allows('hiring.updateCandidate', $candidate),
                'see' => $seesAll,
                'anonymise' => Gate::allows('hiring.delete') && Gate::allows('hiring.updateCandidate', $candidate),
            ],
            'tags' => $seesAll ? HiringTag::query()->orderBy('name')->limit(200)->pluck('name') : [],
            'employmentTypes' => $seesAll ? array_values(EmploymentType::query()->where('is_active', true)->orderBy('id')->get()->map(fn (EmploymentType $t): array => ['id' => $t->ulid, 'name' => Texts::one($t, 'name') ?? ''])->all()) : [],
            'timezone' => $user->timezone ?? config('app.timezone'),
        ]);
    }

    public function message(Request $request, Candidate $candidate, SendCandidateMessage $send, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($candidate);
        $request->validate([
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'subject' => ['nullable', 'required_if:channel,email', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:4000'],
            'application' => ['nullable', 'string', 'size:26'],
        ]);
        $application = $request->filled('application')
            ? JobApplication::query()->where('candidate_id', $candidate->id)->where('ulid', $request->string('application')->lower()->toString())->first()
            : null;
        $message = $send->handle(Actor::user($user), $candidate, $application, MessageChannel::from($request->string('channel')->toString()), $request->string('subject')->toString() ?: null, $request->string('body')->toString(), MessagePurpose::Manual);
        Inertia::flash('toast', $message->status === MessageStatus::Failed
            ? ['type' => 'error', 'message' => __('The message could not be sent: :reason', ['reason' => $message->error ?? ''])]
            : ['type' => 'success', 'message' => __('Message sent.')]);

        return back();
    }

    public function anonymise(Candidate $candidate, AnonymiseCandidate $anonymise, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($candidate);
        abort_unless(Gate::allows('hiring.delete'), 403);
        $anonymise->handle(Actor::user($user), $candidate, 'hr');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The candidate’s details were deleted.')]);

        return to_route('hiring.candidates.index');
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
