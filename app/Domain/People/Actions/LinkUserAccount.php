<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Support\Errors\DomainRuleViolation;

/**
 * Links (or unlinks) a sign-in account to an employee record. One user ↔ at most one employee
 * (D6); users may also exist without one (auditors, interviewers, bots).
 */
final readonly class LinkUserAccount
{
    public function handle(Employee $employee, ?User $user): Employee
    {
        if ($user instanceof User && Employee::query()->where('user_id', $user->id)->whereKeyNot($employee->id)->exists()) {
            throw new DomainRuleViolation('people.user_already_linked', __('This account already belongs to another employee.'), ['userId' => [__('This account already belongs to another employee.')]]);
        }

        $employee->forceFill(['user_id' => $user?->id])->save();

        return $employee;
    }
}
