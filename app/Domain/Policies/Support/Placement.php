<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

/**
 * Where an employee sits on a date: the targets policy assignments can attach to. Department ids
 * run from the root down to the employee's own department.
 */
final readonly class Placement
{
    /**
     * @param  list<int>  $departmentIds
     */
    public function __construct(
        public int $employeeId,
        public ?int $branchId,
        public array $departmentIds,
        public ?int $positionId,
        public ?int $gradeId,
        public ?int $employmentTypeId,
    ) {}
}
