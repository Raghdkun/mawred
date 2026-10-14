<?php

declare(strict_types=1);

namespace App\Domain\Requests\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Models\WorkflowTask;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Who may open a request: the requester, anyone whose `requests.view` scope reaches the
 * requester, and anyone who has (or had) a task on it, so approvers outside the scope can decide.
 */
final readonly class RequestPolicy
{
    public function __construct(private ScopeResolver $resolver) {}

    public function view(User $user, EmployeeRequest $request): bool
    {
        if ($request->requester_user_id === $user->id || $this->hasTask($user, $request)) {
            return true;
        }

        return $this->resolver->allows($user, 'requests.view', $request, SubjectBinding::employee('requests.requester_employee_id'));
    }

    public function withdraw(User $user, EmployeeRequest $request): bool
    {
        return $request->requester_user_id === $user->id;
    }

    public function comment(User $user, EmployeeRequest $request): bool
    {
        return $this->view($user, $request);
    }

    private function hasTask(User $user, EmployeeRequest $request): bool
    {
        return $request->workflow_instance_id !== null && WorkflowTask::query()
            ->where('workflow_instance_id', $request->workflow_instance_id)
            ->where(fn (Builder $q) => $q->where('assignee_user_id', $user->id)->orWhere('original_assignee_user_id', $user->id))
            ->exists();
    }
}
