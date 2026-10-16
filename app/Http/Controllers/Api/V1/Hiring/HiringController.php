<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\MoveApplication;
use App\Domain\Hiring\Data\BoardCardData;
use App\Domain\Hiring\Data\CandidateRowData;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Queries\CandidatesQuery;
use App\Domain\Hiring\Queries\RequisitionsQuery;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Hiring over /api/v1: requisitions and candidates (scoped, cursor-paginated, for pickers and the
 * mobile app), people who can join a hiring team, and moving a candidate between stages.
 */
final class HiringController extends Controller
{
    public function requisitions(Request $request, RequisitionsQuery $query): JsonResponse
    {
        $user = $this->user($request);
        Gate::authorize('hiring.view');
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(RequisitionStatus::class)], 'cursor' => ['nullable', 'string']]);
        $page = $query->withStatuses($request->filled('status') ? [RequisitionStatus::from($request->string('status')->toString())] : [])
            ->search($request->string('search')->toString())
            ->visibleTo($user)
            ->with('branch')
            ->cursorPaginate(20);
        $locale = app()->getLocale();

        return $this->page($page, fn (JobRequisition $r): array => [
            'id' => $r->ulid,
            'number' => $r->number,
            'title' => $r->getTranslation('title', $locale),
            'status' => $r->status->value,
            'branch' => $r->branch?->getTranslation('name', $locale),
        ]);
    }

    public function candidates(Request $request, CandidatesQuery $query): JsonResponse
    {
        $user = $this->user($request);
        Gate::authorize('hiring.view');
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'pool' => ['nullable', 'boolean'], 'cursor' => ['nullable', 'string']]);
        $page = $query->search($request->string('search')->toString())->poolOnly($request->boolean('pool'))->visibleTo($user)
            ->with(['tags', 'applications.requisition', 'applications.stage'])
            ->cursorPaginate(20);

        return $this->page($page, fn ($candidate): CandidateRowData => CandidateRowData::fromCandidate($candidate));
    }

    /** Active users whose role can see hiring: who may join a hiring team. */
    public function people(Request $request): JsonResponse
    {
        $this->user($request);
        Gate::authorize('hiring.update');
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'cursor' => ['nullable', 'string']]);
        $search = trim($request->string('search')->toString());
        $page = User::query()
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q
                ->whereHas('roles', fn (Builder $r) => $r->where('is_super_admin', true))
                ->orWhereHas('roles.permissions', fn (Builder $p) => $p->where('name', 'hiring.view')))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereRaw('lower(name) like ?', ['%'.addcslashes(mb_strtolower($search), '%_\\').'%'])
                ->orWhereRaw('lower(email) like ?', ['%'.addcslashes(mb_strtolower($search), '%_\\').'%'])))
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate(20);

        return $this->page($page, fn (User $u): array => ['id' => $u->ulid, 'name' => $u->name, 'email' => $u->email]);
    }

    public function move(Request $request, JobApplication $application, MoveApplication $move): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(Gate::allows('hiring.viewApplication', $application), 404);
        abort_unless(Gate::allows('hiring.updateApplication', $application), 403);
        $request->validate(['stage' => ['required', 'string', 'size:26']]);
        $stage = PipelineStage::query()->where('ulid', $request->string('stage')->lower()->toString())->where('job_requisition_id', $application->job_requisition_id)->first();
        abort_unless($stage instanceof PipelineStage, 404);

        $moved = $move->handle(Actor::user($user), $application, $stage)->load(['candidate.tags', 'stage']);

        return response()->json(['data' => BoardCardData::fromApplication($moved)]);
    }

    /**
     * @template T
     *
     * @param  CursorPaginator<int, T>  $page
     * @param  callable(T): mixed  $map
     */
    private function page(CursorPaginator $page, callable $map): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_map($map, $page->items())),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'prev_cursor' => $page->previousCursor()?->encode(), 'per_page' => $page->perPage()],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
