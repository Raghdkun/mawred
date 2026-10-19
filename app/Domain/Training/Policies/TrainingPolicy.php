<?php

declare(strict_types=1);

namespace App\Domain\Training\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Models\Enrollment;

/**
 * Training access (ADR-0005). Everyone with a training grant browses the catalog and enrols
 * themselves; trainers and HR (`training.update`) run the catalog and assign people; learners'
 * records are visible to the learner and to whoever holds `training.view` over them.
 */
final readonly class TrainingPolicy
{
    public function __construct(private ScopeResolver $resolver) {}

    public function browse(User $user): bool
    {
        return $this->resolver->can($user, 'training.view');
    }

    public function enrol(User $user): bool
    {
        return $this->resolver->can($user, 'training.create') && Employee::query()->where('user_id', $user->id)->exists();
    }

    public function manage(User $user): bool
    {
        return $this->resolver->can($user, 'training.update');
    }

    public function assign(User $user, Employee $employee): bool
    {
        return $this->allows($user, 'training.update', $employee);
    }

    public function viewRecord(User $user, Employee $employee): bool
    {
        return $employee->user_id === $user->id || $this->allows($user, 'training.view', $employee);
    }

    public function viewEnrollment(User $user, Enrollment $enrollment): bool
    {
        $employee = $enrollment->employee()->first();

        return $employee instanceof Employee && $this->viewRecord($user, $employee);
    }

    public function manageEnrollment(User $user, Enrollment $enrollment): bool
    {
        $employee = $enrollment->employee()->first();

        return $employee instanceof Employee && $this->assign($user, $employee);
    }

    private function allows(User $user, string $permission, Employee $employee): bool
    {
        return $this->resolver->allows($user, $permission, $employee, SubjectBinding::employee('employees.id'));
    }
}
