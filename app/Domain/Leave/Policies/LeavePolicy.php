<?php

declare(strict_types=1);

namespace App\Domain\Leave\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\People\Models\Employee;

/**
 * Leave balances and time away, scoped by `leave.*` grants (ADR-0005). Everyone sees their own
 * balances; adjusting them and cancelling approved leave need `leave.update` over the person.
 */
final readonly class LeavePolicy
{
    public function __construct(private ScopeResolver $resolver) {}

    public function view(User $user, Employee $employee): bool
    {
        return $employee->user_id === $user->id || $this->allows($user, 'leave.view', $employee);
    }

    public function adjust(User $user, Employee $employee): bool
    {
        return $employee->user_id !== $user->id && $this->allows($user, 'leave.update', $employee);
    }

    public function cancel(User $user, LeaveRequest $leave): bool
    {
        $employee = $leave->employee()->first();

        return $employee instanceof Employee && $this->adjust($user, $employee);
    }

    private function allows(User $user, string $permission, Employee $employee): bool
    {
        return $this->resolver->allows($user, $permission, $employee, SubjectBinding::employee('employees.id'));
    }
}
