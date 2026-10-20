<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Leave\Data\LeaveBalanceData;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Leave\Support\LeaveBalances;
use App\Domain\Leave\Support\LeaveYear;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Dashboards\EmployeeDashboard;
use App\Domain\Reports\Dashboards\HrDashboard;
use App\Domain\Reports\Dashboards\ManagerDashboard;
use App\Domain\Reports\Data\EmployeeDashboardData;
use App\Domain\Reports\Data\HrDashboardData;
use App\Domain\Reports\Data\ManagerDashboardData;
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
 * Role-based home (handoff 4 employee, 5 manager, 6 HR admin): requests, approvals and leave
 * balances, plus the role dashboards (D115, D123): the check-in card and agenda, the team today,
 * and the HR KPIs, expiring documents, attendance today and integration health.
 */
final class HomeController extends Controller
{
    public function __invoke(RequestPresenter $presenter, LeaveBalances $balances, EmployeeDays $days, HrDashboard $hr, ManagerDashboard $manager, EmployeeDashboard $mine, #[CurrentUser] User $user): Response
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();
        $layouts = Role::query()->whereIn('name', $user->getRoleNames()->all())->get()->map(fn (Role $role): HomeLayout => $role->home_layout);
        $layout = $layouts->contains(HomeLayout::HrAdmin) ? HomeLayout::HrAdmin : ($layouts->contains(HomeLayout::Manager) ? HomeLayout::Manager : HomeLayout::Employee);
        $tasks = WorkflowTask::query()->where('assignee_user_id', $user->id)->where('status', TaskStatus::Open->value);

        $locale = app()->getLocale();

        return Inertia::render('home/index', [
            'layout' => $layout->value,
            // Dashboards (D115, D123) load after the page, each on its own.
            'hr' => $layout === HomeLayout::HrAdmin ? Inertia::defer(fn (): HrDashboardData => $hr->build($user, $locale), 'hr') : null,
            'team' => $layout === HomeLayout::Manager ? Inertia::defer(fn (): ManagerDashboardData => $manager->build($user, $locale), 'team') : null,
            'mine' => $layout !== HomeLayout::HrAdmin ? Inertia::defer(fn (): EmployeeDashboardData => $mine->build($user, $locale), 'mine') : null,
            'balances' => $employee instanceof Employee ? array_slice(array_values(array_filter(
                $balances->all($employee, LeaveYear::of($days->today($employee))),
                fn (LeaveBalanceData $balance): bool => $balance->entitlement > 0 || $balance->balance !== 0.0,
            )), 0, 3) : [],
            'myRequests' => EmployeeRequest::query()->with(['type', 'requester'])->where('requester_user_id', $user->id)
                ->where(fn (Builder $q) => $q->whereIn('status', [RequestStatus::InReview->value, RequestStatus::Returned->value])->orWhere('decided_at', '>=', now()->subDays(14)))
                ->latest('submitted_at')->limit(5)->get()->map(RequestRowData::fromRequest(...))->values(),
            'quickTypes' => Gate::allows('requests.create') ? RequestType::query()->with(['form', 'workflow'])->where('is_active', true)->orderBy('sort_order')->get()
                ->filter(fn (RequestType $type): bool => $type->isAvailable() && ! $type->isRaisedElsewhere())->map(RequestTypeData::fromType(...))->filter(fn (RequestTypeData $type): bool => $type->ready)->take(6)->values() : [],
            'approvals' => $layout === HomeLayout::Employee && ! (clone $tasks)->exists() ? null : [
                'open' => (clone $tasks)->count(),
                'overdue' => (clone $tasks)->where('due_at', '<', now())->count(),
                'decidedThisWeek' => WorkflowTask::query()->where('decided_by_user_id', $user->id)->where('decided_at', '>=', now()->subDays(7))->count(),
                'tasks' => (clone $tasks)->orderByRaw('due_at is null, due_at')->limit(5)->get()->map(fn (WorkflowTask $task): TaskData => $presenter->task($task, null, $user))->values(),
            ],
        ]);
    }
}
