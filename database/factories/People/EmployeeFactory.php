<?php

declare(strict_types=1);

namespace Database\Factories\People;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Actions\SyncEmployeePlacement;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\People\Support\ReportingLines;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Plain employees by default. `placedIn()` adds a primary assignment from the hire date and
 * syncs the placement and reporting line, as HireEmployee does.
 *
 * @extends Factory<Employee>
 */
final class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'employee_number' => 'EMP-'.fake()->unique()->numerify('9#####'),
            'name_en' => "{$first} {$last}",
            'work_email' => mb_strtolower("{$first}.{$last}.").fake()->unique()->numerify('###').'@mawred.test',
            'hire_date' => '2024-01-01',
            'status' => EmployeeStatus::Active,
        ];
    }

    public function placedIn(Branch $branch, ?Department $department = null, ?Employee $manager = null): self
    {
        return $this->afterCreating(function (Employee $employee) use ($branch, $department, $manager): void {
            resolve(ReportingLines::class)->ensureSelf($employee);

            EmployeeAssignment::query()->create([
                'employee_id' => $employee->id,
                'is_primary' => true,
                'branch_id' => $branch->id,
                'department_id' => $department?->id,
                'manager_employee_id' => $manager?->id,
                'effective_from' => $employee->hire_date->toDateString(),
            ]);

            resolve(SyncEmployeePlacement::class)->handle($employee);
        });
    }

    public function configure(): self
    {
        return $this->afterCreating(fn (Employee $employee) => resolve(ReportingLines::class)->ensureSelf($employee));
    }
}
