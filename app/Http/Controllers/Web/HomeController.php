<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Requests\Data\RequestRowData;
use App\Domain\Requests\Data\RequestTypeData;
use App\Domain\Requests\Data\TaskData;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Requests\Support\RequestPresenter;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Role-based home (handoff 4 employee v1, 5 manager). Clock-in, balances, the agenda and team
 * availability join with Phase 5; the HR admin home (6) with Phase 8.
 */
final class HomeController extends Controller
{
    public function __invoke(RequestPresenter $presenter, #[CurrentUser] User $user): Response
    {
        $layouts = Role::query()->whereIn('name', $user->getRoleNames()->all())->get()->map(fn (Role $role): HomeLayout => $role->home_layout);
        $layout = $layouts->contains(HomeLayout::HrAdmin) ? HomeLayout::HrAdmin : ($layouts->contains(HomeLayout::Manager) ? HomeLayout::Manager : HomeLayout::Employee);
        $tasks = WorkflowTask::query()->where('assignee_user_id', $user->id)->where('status', TaskStatus::Open->value);

        return Inertia::render('home/index', [
            'layout' => $layout->value,
            'myRequests' => EmployeeRequest::query()->with(['type', 'requester'])->where('requester_user_id', $user->id)
                ->where(fn (Builder $q) => $q->whereIn('status', [RequestStatus::InReview->value, RequestStatus::Returned->value])->orWhere('decided_at', '>=', now()->subDays(14)))
                ->latest('submitted_at')->limit(5)->get()->map(RequestRowData::fromRequest(...))->values(),
            'quickTypes' => Gate::allows('requests.create') ? RequestType::query()->with(['form', 'workflow'])->where('is_active', true)->orderBy('sort_order')->get()
                ->filter(fn (RequestType $type): bool => $type->isAvailable())->map(RequestTypeData::fromType(...))->filter(fn (RequestTypeData $type): bool => $type->ready)->take(6)->values() : [],
            'approvals' => $layout === HomeLayout::Employee && ! (clone $tasks)->exists() ? null : [
                'open' => (clone $tasks)->count(),
                'overdue' => (clone $tasks)->where('due_at', '<', now())->count(),
                'decidedThisWeek' => WorkflowTask::query()->where('decided_by_user_id', $user->id)->where('decided_at', '>=', now()->subDays(7))->count(),
                'tasks' => (clone $tasks)->orderByRaw('due_at is null, due_at')->limit(5)->get()->map(fn (WorkflowTask $task): TaskData => $presenter->task($task, null, $user))->values(),
            ],
        ]);
    }
}
