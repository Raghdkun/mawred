<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Requests\Actions\AddRequestComment;
use App\Domain\Requests\Actions\ResubmitRequest;
use App\Domain\Requests\Actions\WithdrawRequest;
use App\Domain\Requests\Data\RequestRowData;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Queries\RequestDirectoryQuery;
use App\Domain\Requests\Support\RequestPresenter;
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
 * My requests + detail (handoff 12), and the requests HR can see (`requests.view`, scoped).
 */
final class MyRequestsController extends Controller
{
    public function index(Request $request, RequestDirectoryQuery $query, #[CurrentUser] User $user): Response
    {
        $request->validate([
            'tab' => ['nullable', Rule::in(['mine', 'all'])],
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $canSeeAll = Gate::allows('requests.view');
        $tab = $canSeeAll && $request->input('tab') === 'all' ? 'all' : 'mine';
        $status = $request->filled('status') ? RequestStatus::from($request->string('status')->toString()) : null;

        $builder = $tab === 'all'
            ? $query->withStatus($status)->search($request->string('search')->toString())->visibleTo($user)
            : EmployeeRequest::query()->with(['type', 'requester'])->where('requester_user_id', $user->id)
                ->when($status !== null, fn ($q) => $q->where('status', $status?->value))
                ->latest('submitted_at')->orderByDesc('id');

        return Inertia::render('requests/index', [
            'requests' => $builder->paginate(25)->withQueryString()->through(fn (EmployeeRequest $r): RequestRowData => RequestRowData::fromRequest($r)),
            'filters' => ['tab' => $tab, 'status' => $status?->value, 'search' => $request->input('search')],
            'can' => ['all' => $canSeeAll, 'create' => Gate::allows('requests.create')],
        ]);
    }

    public function show(EmployeeRequest $employeeRequest, RequestPresenter $presenter, FormContext $context, #[CurrentUser] User $user): Response
    {
        abort_unless(Gate::allows('view', $employeeRequest), 404);

        return Inertia::render('requests/show', [
            ...$presenter->detail($employeeRequest, $user),
            'context' => $employeeRequest->requester_user_id === $user->id ? $context->for($user) : null,
        ]);
    }

    /** A file attached to the request's current answers (an audited read). */
    public function download(EmployeeRequest $employeeRequest, FormUpload $upload, #[CurrentUser] User $user): StreamedResponse
    {
        abort_unless(Gate::allows('view', $employeeRequest) && $upload->form_submission_id === $employeeRequest->form_submission_id, 404);

        activity('requests')->causedBy($user)->performedOn($employeeRequest)->event('file.downloaded')
            ->withProperties(['file' => $upload->ulid])->log('Request file downloaded');

        return Storage::disk(FormUpload::DISK)->download($upload->path, $upload->original_name, ['Content-Type' => $upload->mime]);
    }

    public function resubmit(Request $request, EmployeeRequest $employeeRequest, ResubmitRequest $resubmit, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('view', $employeeRequest), 404);
        $request->validate(['values' => ['array']]);

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $resubmit->handle(Actor::user($user), $employeeRequest, $values);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request sent again.')]);

        return back();
    }

    public function withdraw(Request $request, EmployeeRequest $employeeRequest, WithdrawRequest $withdraw, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('withdraw', $employeeRequest);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $withdraw->handle(Actor::user($user), $employeeRequest, $request->filled('reason') ? $request->string('reason')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request withdrawn.')]);

        return back();
    }

    public function comment(Request $request, EmployeeRequest $employeeRequest, AddRequestComment $add, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('comment', $employeeRequest), 404);
        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $add->handle(Actor::user($user), $employeeRequest, $request->string('body')->toString());

        return back();
    }
}
