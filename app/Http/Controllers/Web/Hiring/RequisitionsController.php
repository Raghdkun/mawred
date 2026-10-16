<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Hiring\Actions\ChangeRequisitionStatus;
use App\Domain\Hiring\Actions\RaiseRequisition;
use App\Domain\Hiring\Actions\SaveRequisitionTeam;
use App\Domain\Hiring\Data\RequisitionData;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\MemberRole;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\RequisitionMember;
use App\Domain\Hiring\Queries\RequisitionsQuery;
use App\Domain\Hiring\Support\Texts;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Data\RequestTypeData;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Requests\Support\RequestPresenter;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Job requisitions (handoff 28, D84): the table with approval status, the drawer with the
 * approval timeline, hiring team and postings, and raising a new one through its request type.
 */
final class RequisitionsController extends Controller
{
    /** Status filter groups shown as tabs. */
    private const array GROUPS = [
        'open' => [RequisitionStatus::Open, RequisitionStatus::OnHold],
        'pending' => [RequisitionStatus::Pending],
        'closed' => [RequisitionStatus::Filled, RequisitionStatus::Closed, RequisitionStatus::Rejected, RequisitionStatus::Cancelled],
        'all' => [],
    ];

    public function index(Request $request, RequisitionsQuery $query, RequestPresenter $presenter, #[CurrentUser] User $user): Response
    {
        Gate::authorize('hiring.view');
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::GROUPS))],
            'search' => ['nullable', 'string', 'max:100'],
            'open' => ['nullable', 'string', 'size:26'],
        ]);
        $group = $request->string('status', 'open')->toString();

        $page = $query->withStatuses(self::GROUPS[$group])->search($request->string('search')->toString())->visibleTo($user)
            ->with(['branch', 'department', 'position', 'hiringManager', 'request'])
            ->withCount([
                'applications',
                'applications as active_count' => fn (Builder $q) => $q->where('status', ApplicationStatus::Active->value),
                'postings' => fn (Builder $q) => $q->where('status', 'published'),
            ])
            ->paginate(25)
            ->withQueryString();

        $open = $request->filled('open') ? JobRequisition::query()->where('ulid', $request->string('open')->lower()->toString())->first() : null;

        return Inertia::render('hiring/requisitions', [
            'requisitions' => [
                'data' => array_values(array_map(RequisitionData::fromRequisition(...), $page->items())),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ],
            'filters' => ['status' => $group, 'search' => $request->string('search')->toString()],
            'detail' => $open instanceof JobRequisition && Gate::allows('hiring.viewRequisition', $open) ? $this->detail($open, $presenter, $user) : null,
            'can' => ['create' => $this->canRaise($user)],
        ]);
    }

    public function create(FormContext $context, #[CurrentUser] User $user): Response
    {
        abort_unless($this->canRaise($user), 403);
        $type = $this->type();
        $version = $type->form()->first()?->publishedVersion()->first();
        abort_unless($version instanceof FormVersion, 404);

        return Inertia::render('hiring/requisition-create', [
            'type' => RequestTypeData::fromType($type->load(['form', 'workflow'])),
            'schema' => $version->schema,
            'context' => $context->for($user),
        ]);
    }

    public function store(Request $request, RaiseRequisition $raise, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($this->canRaise($user), 403);
        $request->validate(['values' => ['array']]);

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $requisition = $raise->handle(Actor::user($user), $values);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition sent for approval.')]);

        return to_route('hiring.requisitions.index', ['status' => 'pending', 'open' => $requisition->ulid]);
    }

    public function status(Request $request, JobRequisition $requisition, ChangeRequisitionStatus $change, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($requisition);
        $request->validate(['status' => ['required', Rule::enum(RequisitionStatus::class)]]);
        $change->handle(Actor::user($user), $requisition, RequisitionStatus::from($request->string('status')->toString()));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition updated.')]);

        return back();
    }

    public function team(Request $request, JobRequisition $requisition, SaveRequisitionTeam $save, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($requisition);
        $request->validate([
            'members' => ['present', 'array', 'max:30'],
            'members.*.user' => ['required', 'string', 'size:26'],
            'members.*.role' => ['required', Rule::enum(MemberRole::class)],
        ]);

        /** @var list<array{user: string, role: string}> $members */
        $members = $request->input('members', []);
        $save->handle(Actor::user($user), $requisition, $members);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Hiring team saved.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(JobRequisition $requisition, RequestPresenter $presenter, User $user): array
    {
        $requisition->loadCount([
            'applications',
            'applications as active_count' => fn (Builder $q) => $q->where('status', ApplicationStatus::Active->value),
            'postings' => fn (Builder $q) => $q->where('status', 'published'),
        ]);
        $instance = $requisition->request?->instance()->first();
        $locale = app()->getLocale();

        return [
            'requisition' => RequisitionData::fromRequisition($requisition),
            'timeline' => $instance instanceof WorkflowInstance ? $presenter->timeline($instance) : [],
            'members' => array_values($requisition->members()->with('user')->get()->map(fn (RequisitionMember $m): array => [
                'user' => $m->user->ulid ?? '',
                'name' => $m->user->name ?? '—',
                'role' => $m->role->value,
            ])->all()),
            'postings' => array_values($requisition->postings()->withCount('applications')->latest()->get()->map(fn (JobPosting $p): array => [
                'id' => $p->ulid,
                'title' => Texts::one($p, 'title', $locale) ?? '',
                'status' => $p->status->value,
                'url' => route('careers.show', $p->slug),
                'applicants' => Texts::count($p, 'applications_count'),
            ])->all()),
            'next' => array_map(fn (RequisitionStatus $s): string => $s->value, $requisition->status->next()),
            'can' => ['update' => Gate::forUser($user)->allows('hiring.updateRequisition', $requisition)],
        ];
    }

    private function authorizeUpdate(JobRequisition $requisition): void
    {
        abort_unless(Gate::allows('hiring.viewRequisition', $requisition), 404);
        abort_unless(Gate::allows('hiring.updateRequisition', $requisition), 403);
    }

    private function canRaise(User $user): bool
    {
        return Gate::forUser($user)->allows('hiring.create')
            && Employee::query()->where('user_id', $user->id)->exists()
            && RequestType::query()->where('code', JobRequisition::REQUEST_TYPE)->where('is_active', true)->exists();
    }

    private function type(): RequestType
    {
        return RequestType::query()->where('code', JobRequisition::REQUEST_TYPE)->where('is_active', true)->firstOrFail();
    }
}
