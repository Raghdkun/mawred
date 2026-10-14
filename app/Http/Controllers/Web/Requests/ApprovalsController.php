<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Requests\Data\TaskData;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Support\RequestPresenter;
use App\Domain\Workflows\Actions\DecideTask;
use App\Domain\Workflows\Actions\DelegateTask;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskKind;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Http\Controllers\Controller;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Approvals inbox (handoff 13): my open tasks, the selected one in detail, decide, delegate and
 * bulk approve.
 */
final class ApprovalsController extends Controller
{
    public function index(Request $request, RequestPresenter $presenter, #[CurrentUser] User $user): Response
    {
        $request->validate(['task' => ['nullable', 'string', 'size:26']]);

        $tasks = WorkflowTask::query()->where('assignee_user_id', $user->id)->where('status', TaskStatus::Open->value)
            ->orderByRaw('due_at is null, due_at')->orderBy('id')->limit(200)->get();
        $selected = $request->filled('task') ? $tasks->firstWhere('ulid', $request->string('task')->toString()) : $tasks->first();
        $selectedRequest = $selected instanceof WorkflowTask && $selected->owner()->subject_type === EmployeeRequest::class
            ? EmployeeRequest::query()->find($selected->owner()->subject_id)
            : null;

        return Inertia::render('approvals/index', [
            'tasks' => $tasks->map(fn (WorkflowTask $task): TaskData => $presenter->task($task, null, $user))->values(),
            'selected' => $selected instanceof WorkflowTask ? $presenter->task($selected, $selectedRequest, $user) : null,
            'detail' => $selectedRequest instanceof EmployeeRequest ? $presenter->detail($selectedRequest, $user) : null,
        ]);
    }

    public function decide(Request $request, WorkflowTask $task, DecideTask $decide, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($task->assignee_user_id === $user->id, 404);
        $request->validate([
            'decision' => ['required', Rule::enum(Decision::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $decision = Decision::from($request->string('decision')->toString());
        $decide->handle($task, $user, $decision, $request->filled('comment') ? $request->string('comment')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => match ($decision) {
            Decision::Approved => __('Approved.'),
            Decision::Rejected => __('Rejected.'),
            Decision::Returned => __('Sent back for changes.'),
            Decision::Done => __('Marked as done.'),
        }]);

        return to_route('approvals.index');
    }

    /** Bulk approve (handoff 13): approvals only, each decided on its own. */
    public function bulk(Request $request, DecideTask $decide, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate(['tasks' => ['required', 'array', 'min:1', 'max:100'], 'tasks.*' => ['string', 'size:26']]);

        /** @var list<string> $ids */
        $ids = $request->input('tasks');
        $count = 0;

        foreach (WorkflowTask::query()->whereIn('ulid', $ids)->where('assignee_user_id', $user->id)->where('status', TaskStatus::Open->value)->where('kind', TaskKind::Approval->value)->get() as $task) {
            try {
                $decide->handle($task, $user, Decision::Approved);
                $count++;
            } catch (DomainRuleViolation) {
                continue;
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} :count request approved.|[2,*] :count requests approved.', $count, ['count' => $count])]);

        return to_route('approvals.index');
    }

    public function delegate(Request $request, WorkflowTask $task, DelegateTask $delegate, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($task->assignee_user_id === $user->id, 404);
        $request->validate(['to' => ['required', 'string', 'exists:users,ulid'], 'comment' => ['nullable', 'string', 'max:500']]);

        $to = User::query()->where('ulid', $request->string('to')->toString())->firstOrFail();
        $delegate->handle($task, $user, $to, $request->filled('comment') ? $request->string('comment')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delegated to :name.', ['name' => $to->name])]);

        return to_route('approvals.index');
    }
}
