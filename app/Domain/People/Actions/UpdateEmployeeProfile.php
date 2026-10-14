<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Data\EmployeeProfileInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Support\EmployeeContact;
use App\Support\Errors\DomainRuleViolation;

final readonly class UpdateEmployeeProfile
{
    public function __construct(private SyncEmployeePlacement $sync) {}

    public function handle(Employee $employee, EmployeeProfileInputData $data): Employee
    {
        $region = $employee->current_branch_id === null ? null : Branch::withTrashed()->whereKey($employee->current_branch_id)->value('phone_region');
        $hireDate = $data->hireDate ?? $employee->hire_date->toDateString();

        if ($data->probationEndsOn !== null && $data->probationEndsOn < $hireDate) {
            throw new DomainRuleViolation('people.dates_reversed', __('Probation must end on or after the hire date.'), ['probationEndsOn' => [__('Probation must end on or after the hire date.')]]);
        }

        $firstPlacement = $employee->assignments()->where('is_primary', true)->min('effective_from');

        if (is_string($firstPlacement) && $hireDate > substr($firstPlacement, 0, 10)) {
            throw new DomainRuleViolation('people.hire_after_placement', __('The hire date cannot be after the first placement.'), ['hireDate' => [__('The hire date cannot be after the first placement.')]]);
        }

        $employee->fill([
            'name_en' => trim($data->nameEn),
            'name_ar' => $this->clean($data->nameAr),
            'preferred_name' => $this->clean($data->preferredName),
            'work_email' => EmployeeContact::email($data->workEmail, $employee),
            'work_phone_e164' => EmployeeContact::phone($data->workPhone, is_string($region) ? $region : null),
            'gender' => $data->gender,
            'nationality' => $data->nationality === null ? null : mb_strtoupper($data->nationality),
            'marital_status' => $data->maritalStatus,
            'hire_date' => $hireDate,
            'probation_ends_on' => $data->probationEndsOn,
        ])->save();

        return $employee->wasChanged('hire_date') ? $this->sync->handle($employee) : $employee;
    }

    private function clean(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
