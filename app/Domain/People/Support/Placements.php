<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Support\Errors\DomainRuleViolation;

/**
 * Turns public ULIDs from a placement form into assignment columns, refusing archived or
 * inactive reference rows and managers who have left.
 */
final class Placements
{
    /**
     * @return array{branch_id: int, department_id: int|null, position_id: int|null, manager_employee_id: int|null, employment_type_id: int|null, grade_id: int|null}
     */
    public static function resolve(PlacementInputData $input): array
    {
        $manager = $input->managerId === null ? null : Employee::query()->where('ulid', $input->managerId)->first();

        if ($input->managerId !== null && ($manager === null || $manager->status === EmployeeStatus::Terminated)) {
            throw new DomainRuleViolation('people.manager_unavailable', __('Choose a manager who still works here.'), ['managerId' => [__('Choose a manager who still works here.')]]);
        }

        return [
            'branch_id' => self::id(Branch::class, $input->branchId, 'branchId'),
            'department_id' => $input->departmentId === null ? null : self::id(Department::class, $input->departmentId, 'departmentId'),
            'position_id' => $input->positionId === null ? null : self::id(Position::class, $input->positionId, 'positionId'),
            'manager_employee_id' => $manager?->id,
            'employment_type_id' => $input->employmentTypeId === null ? null : self::id(EmploymentType::class, $input->employmentTypeId, 'employmentTypeId'),
            'grade_id' => $input->gradeId === null ? null : self::id(Grade::class, $input->gradeId, 'gradeId'),
        ];
    }

    /**
     * @param  class-string<Branch|Department|Position|EmploymentType|Grade>  $model
     */
    private static function id(string $model, string $ulid, string $field): int
    {
        $id = $model::query()->where('ulid', $ulid)->where('is_active', true)->value('id');

        if (! is_numeric($id)) {
            throw self::invalid($field);
        }

        return (int) $id;
    }

    private static function invalid(string $field): DomainRuleViolation
    {
        return new DomainRuleViolation('people.placement_invalid', __('This option is archived or inactive.'), [$field => [__('This option is archived or inactive.')]]);
    }
}
