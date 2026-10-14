<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Requests\Data\TaskData;
use App\Domain\Requests\Support\RequestPresenter;
use App\Domain\Workflows\Actions\DecideTask;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\DecisionChannel;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Requests\TaskResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** The approvals inbox for the mobile app: open tasks and deciding them (ADR-0009). */
final class ApprovalsController extends Controller
{
    public function index(Request $request, RequestPresenter $presenter, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $page = WorkflowTask::query()->where('assignee_user_id', $user->id)->where('status', TaskStatus::Open->value)
            ->orderBy('id')->cursorPaginate($request->integer('per_page', 25))->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (WorkflowTask $task): TaskData => $presenter->task($task, null, $user)));

        return TaskResource::collection($page);
    }

    public function decide(Request $request, WorkflowTask $task, DecideTask $decide, RequestPresenter $presenter, #[CurrentUser] User $user): TaskResource
    {
        abort_unless($task->assignee_user_id === $user->id, 404);
        $request->validate([
            'decision' => ['required', Rule::enum(Decision::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $decide->handle($task, $user, Decision::from($request->string('decision')->toString()), $request->filled('comment') ? $request->string('comment')->toString() : null, DecisionChannel::Api);

        return new TaskResource($presenter->task($task->refresh(), null, $user));
    }
}
