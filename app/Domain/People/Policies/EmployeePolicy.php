<?php

declare(strict_types=1);

namespace App\Domain\People\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Models\Employee;

/**
 * Record-level checks for one employee: the viewer's grant for the action must reach this person
 * through its data scope (ADR-0005).
 */
final readonly class EmployeePolicy
{
    public function __construct(private ScopeResolver $resolver) {}

    public function view(User $user, Employee $employee): bool
    {
        return $this->allows($user, 'people.view', $employee);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->allows($user, 'people.update', $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->allows($user, 'people.delete', $employee);
    }

    private function allows(User $user, string $permission, Employee $employee): bool
    {
        return $this->resolver->allows($user, $permission, $employee, SubjectBinding::employee('employees.id'));
    }
}
