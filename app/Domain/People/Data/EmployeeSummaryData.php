<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\Employee;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Directory row / card / picker item (screen 7). Never carries sensitive fields.
 */
#[TypeScript]
final class EmployeeSummaryData extends Data
{
    public function __construct(
        public string $id,
        public string $number,
        public string $nameEn,
        public ?string $nameAr,
        public ?string $preferredName,
        public ?string $workEmail,
        public ?string $workPhone,
        public string $status,
        public string $hireDate,
        public ?RefData $branch,
        public ?RefData $department,
        public ?RefData $position,
        public ?PersonRefData $manager,
    ) {}

    public static function fromEmployee(Employee $employee): self
    {
        return new self(
            id: $employee->ulid,
            number: $employee->employee_number,
            nameEn: $employee->name_en,
            nameAr: $employee->name_ar,
            preferredName: $employee->preferred_name,
            workEmail: $employee->work_email,
            workPhone: $employee->work_phone_e164,
            status: $employee->status->value,
            hireDate: $employee->hire_date->toDateString(),
            branch: RefData::of($employee->currentBranch),
            department: RefData::of($employee->currentDepartment),
            position: RefData::of($employee->currentPosition),
            manager: PersonRefData::of($employee->currentManager),
        );
    }
}
