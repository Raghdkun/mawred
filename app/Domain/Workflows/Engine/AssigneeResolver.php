<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Models\WorkflowInstance;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Who a task goes to (architecture §6). Specs are JSON in node config:
 *
 * - `{type: user, user: <ulid>}` · `{type: requester}`
 * - `{type: role, role: hr_officer, scoped: true}`: holders of the role; `scoped` keeps those
 *   whose `requests.approve` scope covers the requester (e.g. that branch's HR officer)
 * - `{type: manager}` · `{type: manager_level, level: 2}` · `{type: department_head}` ·
 *   `{type: branch_manager}` · `{type: field, field: <employee picker key>}`
 * - `{type: round_robin, role: …, scoped: …}`: one holder in turn
 * - `{type: assignee_manager}`: the current assignee's manager (SLA escalation)
 */
final readonly class AssigneeResolver
{
    public const array TYPES = ['user', 'requester', 'role', 'manager', 'manager_level', 'department_head', 'branch_manager', 'field', 'round_robin', 'assignee_manager'];

    public function __construct(private ScopeResolver $scopes, private Cache $cache) {}

    /**
     * @param  array<mixed>  $spec
     * @return list<int> user ids, possibly empty
     */
    public function resolve(array $spec, WorkflowInstance $instance, ?int $currentAssignee = null, string $cursorKey = ''): array
    {
        $requester = $instance->requester_employee_id === null ? null : Employee::withTrashed()->find($instance->requester_employee_id);
        $type = is_string($spec['type'] ?? null) ? $spec['type'] : '';

        $ids = match ($type) {
            'user' => $this->userByUlid($spec['user'] ?? null),
            'requester' => $instance->requester_user_id === null ? [] : [$instance->requester_user_id],
            'role' => $this->roleHolders($spec, $requester),
            'manager' => $this->managerUsers($requester, 1),
            'manager_level' => $this->managerUsers($requester, is_int($spec['level'] ?? null) ? max(1, min(10, $spec['level'])) : 1),
            'department_head' => $this->departmentHead($requester),
            'branch_manager' => $this->employeeUser($requester?->current_branch_id === null ? null : Branch::withTrashed()->find($requester->current_branch_id)?->manager_employee_id),
            'field' => $this->fieldEmployee($spec['field'] ?? null, $instance),
            'round_robin' => $this->roundRobin($spec, $requester, $cursorKey),
            'assignee_manager' => $this->assigneeManager($currentAssignee),
            default => [],
        };

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => User::query()->whereKey($id)->exists())));
    }

    /**
     * Holders of the fallback role (config `workflows.fallback_role`), used when nobody resolves.
     *
     * @return list<int>
     */
    public function fallback(): array
    {
        $role = config('workflows.fallback_role', 'hr_admin');

        return $this->roleHolders(['role' => is_string($role) ? $role : 'hr_admin', 'scoped' => false], null);
    }

    /**
     * @return list<int>
     */
    private function userByUlid(mixed $ulid): array
    {
        $id = is_string($ulid) ? User::query()->where('ulid', $ulid)->value('id') : null;

        return is_int($id) ? [$id] : [];
    }

    /**
     * @param  array<mixed>  $spec
     * @return list<int>
     */
    private function roleHolders(array $spec, ?Employee $requester): array
    {
        $role = is_string($spec['role'] ?? null) ? $spec['role'] : null;

        if ($role === null) {
            return [];
        }

        $users = User::role($role)->orderBy('id')->get();

        if (($spec['scoped'] ?? false) === true && $requester instanceof Employee) {
            $users = $users->filter(fn (User $user): bool => $this->scopes->allows($user, 'requests.approve', $requester, SubjectBinding::employee('employees.id')));
        }

        return array_values($users->map(fn (User $user): int => $user->id)->all());
    }

    /**
     * @return list<int>
     */
    private function managerUsers(?Employee $employee, int $levels): array
    {
        $current = $employee;

        for ($i = 0; $i < $levels && $current instanceof Employee; $i++) {
            $current = $current->current_manager_id === null ? null : Employee::withTrashed()->find($current->current_manager_id);
        }

        return $current instanceof Employee && $current->user_id !== null ? [$current->user_id] : [];
    }

    /**
     * The nearest department up the tree with a head.
     *
     * @return list<int>
     */
    private function departmentHead(?Employee $employee): array
    {
        $department = $employee?->current_department_id === null ? null : Department::withTrashed()->find($employee->current_department_id);

        while ($department instanceof Department) {
            if ($department->head_employee_id !== null && $department->head_employee_id !== $employee?->id) {
                return $this->employeeUser($department->head_employee_id);
            }

            $department = $department->parent_id === null ? null : Department::withTrashed()->find($department->parent_id);
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function employeeUser(?int $employeeId): array
    {
        $userId = $employeeId === null ? null : Employee::withTrashed()->whereKey($employeeId)->value('user_id');

        return is_int($userId) ? [$userId] : [];
    }

    /**
     * @return list<int>
     */
    private function fieldEmployee(mixed $field, WorkflowInstance $instance): array
    {
        $fields = is_array($instance->context['field'] ?? null) ? $instance->context['field'] : [];
        $ulid = is_string($field) ? ($fields[$field] ?? null) : null;
        $userId = is_string($ulid) ? Employee::query()->where('ulid', $ulid)->value('user_id') : null;

        return is_int($userId) ? [$userId] : [];
    }

    /**
     * @param  array<mixed>  $spec
     * @return list<int>
     */
    private function roundRobin(array $spec, ?Employee $requester, string $cursorKey): array
    {
        $holders = $this->roleHolders($spec, $requester);

        if ($holders === []) {
            return [];
        }

        $turn = (int) $this->cache->increment("wf:rr:{$cursorKey}");

        return [$holders[($turn - 1) % count($holders)]];
    }

    /**
     * @return list<int>
     */
    private function assigneeManager(?int $userId): array
    {
        $employee = $userId === null ? null : Employee::query()->where('user_id', $userId)->first();

        return $this->managerUsers($employee, 1);
    }
}
