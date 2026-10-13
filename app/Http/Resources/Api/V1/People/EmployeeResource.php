<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\People;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Directory entry for the mobile app and integrations. No sensitive fields.
 *
 * @property-read Employee $resource
 */
final class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = $this->resource;

        return [
            'id' => $employee->ulid,
            'employee_number' => $employee->employee_number,
            'name' => ['en' => $employee->name_en, 'ar' => $employee->name_ar],
            'preferred_name' => $employee->preferred_name,
            'work_email' => $employee->work_email,
            'work_phone' => $employee->work_phone_e164,
            'status' => $employee->status->value,
            'hire_date' => $employee->hire_date->toDateString(),
            'branch' => $this->ref($employee->currentBranch),
            'department' => $this->ref($employee->currentDepartment),
            'position' => $this->ref($employee->currentPosition),
            'manager' => $employee->currentManager === null ? null : [
                'id' => $employee->currentManager->ulid,
                'name' => ['en' => $employee->currentManager->name_en, 'ar' => $employee->currentManager->name_ar],
            ],
        ];
    }

    /**
     * @return array{id: string, code: string, name: mixed}|null
     */
    private function ref(Branch|Department|Position|null $record): ?array
    {
        if ($record === null) {
            return null;
        }

        return ['id' => $record->ulid, 'code' => $record->code, 'name' => $record->getTranslations($record instanceof Position ? 'title' : 'name')];
    }
}
