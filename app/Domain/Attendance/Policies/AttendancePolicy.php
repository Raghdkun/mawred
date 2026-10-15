<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Models\Employee;

/**
 * Attendance records, scoped by `attendance.*` grants (ADR-0005). People see their own days;
 * correcting needs `attendance.update` over someone else (your own goes through a request).
 */
final readonly class AttendancePolicy
{
    public function __construct(private ScopeResolver $resolver) {}

    public function view(User $user, Employee $employee): bool
    {
        return $employee->user_id === $user->id || $this->allows($user, 'attendance.view', $employee);
    }

    public function correct(User $user, Employee $employee): bool
    {
        return $employee->user_id !== $user->id && $this->allows($user, 'attendance.update', $employee);
    }

    private function allows(User $user, string $permission, Employee $employee): bool
    {
        return $this->resolver->allows($user, $permission, $employee, SubjectBinding::employee('employees.id'));
    }
}
