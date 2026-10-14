<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Events\EmployeeHired;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\People\Support\EmployeeContact;
use App\Domain\People\Support\EmployeeNumbers;
use App\Domain\People\Support\History;
use App\Domain\People\Support\Placements;
use App\Domain\People\Support\ReportingLines;
use App\Domain\Policies\Data\Values\ProbationValue;
use App\Domain\Policies\Support\PolicyResolver;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Adds an employee with their first primary assignment, starting on the hire date.
 */
final readonly class HireEmployee
{
    public function __construct(
        private ConnectionInterface $db,
        private EmployeeNumbers $numbers,
        private ReportingLines $lines,
        private SyncEmployeePlacement $sync,
        private PolicyResolver $policies,
    ) {}

    public function handle(Actor $actor, HireEmployeeData $data): Employee
    {
        $placement = Placements::resolve($data->placement);
        $region = Branch::query()->whereKey($placement['branch_id'])->value('phone_region');

        $number = $data->employeeNumber === null ? null : EmployeeNumbers::normalise($data->employeeNumber);

        if ($number !== null && Employee::withTrashed()->where('employee_number', $number)->exists()) {
            throw new DomainRuleViolation('people.number_taken', __('This employee number is already used.'), ['employeeNumber' => [__('This employee number is already used.')]]);
        }

        $email = EmployeeContact::email($data->workEmail);
        $phone = EmployeeContact::phone($data->workPhone, is_string($region) ? $region : null);

        return $this->db->transaction(function () use ($actor, $data, $placement, $number, $email, $phone): Employee {
            $employee = Employee::query()->create([
                'employee_number' => $number ?? $this->numbers->next(),
                'name_en' => trim($data->nameEn),
                'name_ar' => $this->clean($data->nameAr),
                'preferred_name' => $this->clean($data->preferredName),
                'work_email' => $email,
                'work_phone_e164' => $phone,
                'gender' => $data->gender,
                'nationality' => $data->nationality === null ? null : mb_strtoupper($data->nationality),
                'hire_date' => $data->hireDate,
                'probation_ends_on' => $data->probationEndsOn,
                'status' => EmployeeStatus::Pending,
            ]);

            $this->lines->ensureSelf($employee);

            EmployeeAssignment::query()->create([
                ...$placement,
                'employee_id' => $employee->id,
                'is_primary' => true,
                'effective_from' => $data->hireDate,
                'reason' => 'hired',
                'created_by' => $actor->user?->id,
            ]);

            History::record($actor, $employee, HistoryEvent::Hired, $data->hireDate, $this->changes($data));
            $employee = $this->sync->handle($employee);

            if ($employee->probation_ends_on === null) {
                // Default probation comes from policy (employment.probation) at the hire date.
                $probation = $this->policies->value($employee, $data->hireDate, 'employment.probation');

                if ($probation instanceof ProbationValue && $probation->probationDays > 0) {
                    $employee->forceFill(['probation_ends_on' => CarbonImmutable::parse($data->hireDate)->addDays($probation->probationDays - 1)->toDateString()])->save();
                }
            }

            event(new EmployeeHired($employee, $actor));

            return $employee;
        });
    }

    /**
     * @return array<string, string|null>
     */
    private function changes(HireEmployeeData $data): array
    {
        return array_filter([
            'branch' => $data->placement->branchId,
            'department' => $data->placement->departmentId,
            'position' => $data->placement->positionId,
            'manager' => $data->placement->managerId,
        ], fn (?string $v): bool => $v !== null);
    }

    private function clean(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
