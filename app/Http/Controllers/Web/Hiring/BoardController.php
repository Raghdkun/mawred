<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Hiring\Actions\AddApplicationNote;
use App\Domain\Hiring\Actions\MoveApplication;
use App\Domain\Hiring\Actions\RejectApplication;
use App\Domain\Hiring\Actions\ReopenApplication;
use App\Domain\Hiring\Actions\SavePipelineStages;
use App\Domain\Hiring\Actions\SubmitApplication;
use App\Domain\Hiring\Data\BoardCardData;
use App\Domain\Hiring\Data\RequisitionData;
use App\Domain\Hiring\Data\StageData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\RejectionReason;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Queries\ApplicationsQuery;
use App\Domain\Hiring\Support\HiringSettings;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The pipeline kanban (handoff 29 ★): a requisition's stages as columns with their candidates,
 * moving and rejecting candidates, stage settings, and adding a candidate by hand.
 */
final class BoardController extends Controller
{
    public function show(Request $request, JobRequisition $requisition, ApplicationsQuery $applications, HiringSettings $settings, FormContext $context, #[CurrentUser] User $user): Response
    {
        abort_unless(Gate::allows('hiring.viewRequisition', $requisition), 404);
        $request->validate(['rejected' => ['nullable', 'boolean']]);
        $showRejected = $request->boolean('rejected');

        $requisition->loadCount([
            'applications',
            'applications as active_count' => fn (Builder $q) => $q->where('status', ApplicationStatus::Active->value),
            'postings' => fn (Builder $q) => $q->where('status', 'published'),
        ]);
        $cards = $applications->forRequisition($requisition->id)
            ->withStatuses($showRejected ? [ApplicationStatus::Rejected, ApplicationStatus::Withdrawn] : [ApplicationStatus::Active, ApplicationStatus::Hired])
            ->visibleTo($user)
            ->with(['candidate.tags', 'stage'])
            ->limit(2000)
            ->get();
        $canUpdate = Gate::allows('hiring.updateRequisition', $requisition);
        $form = $settings->defaultForm();
        $version = $form instanceof Form ? $form->publishedVersion()->first() : null;
        $locale = app()->getLocale();

        return Inertia::render('hiring/board', [
            'requisition' => RequisitionData::fromRequisition($requisition),
            'stages' => array_values($requisition->stages()->get()->map(StageData::fromStage(...))->all()),
            'cards' => array_values($cards->map(BoardCardData::fromApplication(...))->all()),
            'showRejected' => $showRejected,
            'postings' => array_values($requisition->postings()->latest()->get()->map(fn (JobPosting $p): array => [
                'id' => $p->ulid, 'title' => $p->getTranslation('title', $locale), 'status' => $p->status->value,
            ])->all()),
            'addForm' => $canUpdate && $version instanceof FormVersion ? ['schema' => $version->schema, 'context' => ['requester' => null, 'ctx' => $context->forEmployee(null)['ctx']]] : null,
            'can' => ['update' => $canUpdate, 'open' => $requisition->status->isActive()],
        ]);
    }

    public function move(Request $request, JobApplication $application, MoveApplication $move, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($application);
        $request->validate(['stage' => ['required', 'string', 'size:26']]);
        $stage = PipelineStage::query()->where('ulid', $request->string('stage')->lower()->toString())->where('job_requisition_id', $application->job_requisition_id)->firstOrFail();
        $move->handle(Actor::user($user), $application, $stage);

        return back();
    }

    public function reject(Request $request, RejectApplication $reject, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate([
            'applications' => ['required', 'array', 'min:1', 'max:200'],
            'applications.*' => ['string', 'size:26'],
            'reason' => ['required', Rule::enum(RejectionReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $reason = RejectionReason::from($request->string('reason')->toString());
        /** @var list<string> $ids */
        $ids = $request->input('applications');
        $rejected = 0;

        foreach (JobApplication::query()->whereIn('ulid', array_map(strtolower(...), $ids))->get() as $application) {
            $this->authorizeUpdate($application);

            if ($application->status === ApplicationStatus::Active) {
                $reject->handle(Actor::user($user), $application, $reason, $request->string('note')->toString());
                $rejected++;
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count candidate rejected.|:count candidates rejected.', $rejected, ['count' => $rejected])]);

        return back();
    }

    public function reopen(JobApplication $application, ReopenApplication $reopen, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($application);
        $reopen->handle(Actor::user($user), $application);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application reopened.')]);

        return back();
    }

    public function note(Request $request, JobApplication $application, AddApplicationNote $note, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('hiring.viewApplication', $application), 404);
        $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $note->handle(Actor::user($user), $application, $request->string('body')->toString());

        return back();
    }

    public function stages(Request $request, JobRequisition $requisition, SavePipelineStages $save, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('hiring.viewRequisition', $requisition), 404);
        abort_unless(Gate::allows('hiring.configure') || Gate::allows('hiring.updateRequisition', $requisition), 403);
        $save->handle(Actor::user($user), $requisition, $this->validatedStages($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stages saved.')]);

        return back();
    }

    public function store(Request $request, JobRequisition $requisition, SubmitApplication $submit, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('hiring.viewRequisition', $requisition), 404);
        abort_unless(Gate::allows('hiring.updateRequisition', $requisition), 403);
        $request->validate([
            'values' => ['array'],
            'source' => ['required', Rule::in([ApplicationSource::Manual->value, ApplicationSource::Referral->value])],
        ]);

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $submit->handle(Actor::user($user), $requisition, null, $values, ApplicationSource::from($request->string('source')->toString()));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Candidate added.')]);

        return back();
    }

    public function upload(Request $request, JobRequisition $requisition, HiringSettings $settings, StoreFormUpload $store, #[CurrentUser] User $user): JsonResponse
    {
        abort_unless(Gate::allows('hiring.viewRequisition', $requisition), 404);
        abort_unless(Gate::allows('hiring.updateRequisition', $requisition), 403);
        $request->validate(['field' => ['required', 'string', 'max:40'], 'file' => ['required', 'file', 'max:20480']]);
        $form = $settings->defaultForm();
        $version = $form?->publishedVersion()->first();
        abort_unless($form instanceof Form && $version instanceof FormVersion, 404);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $upload = $store->handle($form, $version, $request->string('field')->toString(), $file, $user, null);

        return response()->json(['id' => $upload->ulid, 'name' => $upload->original_name, 'size' => $upload->size, 'mime' => $upload->mime], 201);
    }

    /**
     * @return list<array{id?: string|null, name: array{en: string, ar?: string|null}, kind: string}>
     */
    public static function validatedStages(Request $request): array
    {
        $request->validate([
            'stages' => ['required', 'array', 'min:2', 'max:12'],
            'stages.*.id' => ['nullable', 'string', 'size:26'],
            'stages.*.name.en' => ['required', 'string', 'max:40'],
            'stages.*.name.ar' => ['nullable', 'string', 'max:40'],
            'stages.*.kind' => ['required', Rule::enum(StageKind::class)],
        ]);

        /** @var list<array{id?: string|null, name: array{en: string, ar?: string|null}, kind: string}> $stages */
        $stages = $request->input('stages');

        return $stages;
    }

    private function authorizeUpdate(JobApplication $application): void
    {
        abort_unless(Gate::allows('hiring.viewApplication', $application), 404);
        abort_unless(Gate::allows('hiring.updateApplication', $application), 403);
    }
}
