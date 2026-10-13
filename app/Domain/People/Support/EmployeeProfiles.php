<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\People\Data\CustomFieldValueData;
use App\Domain\People\Data\EmployeeProfileData;
use App\Domain\People\Data\EmployeeSummaryData;
use App\Domain\People\Data\RefData;
use App\Domain\People\Data\SensitiveDetailsData;
use App\Domain\People\Models\CustomFieldDefinition;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeCustomValue;
use App\Domain\People\Models\UserInvitation;

/**
 * Builds the profile one viewer may see: sensitive groups and sensitive custom fields only with
 * field access, and every sensitive value shown is audited once per request.
 */
final readonly class EmployeeProfiles
{
    public function __construct(private ScopeResolver $resolver, private SensitiveReads $reads) {}

    public function for(User $viewer, Employee $employee): EmployeeProfileData
    {
        $employee->loadMissing([
            'currentBranch', 'currentDepartment', 'currentPosition', 'currentManager',
            'currentEmploymentType', 'currentGrade', 'user:id,email', 'customValues',
        ]);

        $shown = [];
        $sensitive = $this->sensitive($viewer, $employee, $shown);
        $customFields = $this->customFields($viewer, $employee, $shown);

        $this->reads->record($viewer, $employee, $shown);

        $invitation = $employee->user_id === null
            ? UserInvitation::query()->where('employee_id', $employee->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->first()
            : null;

        return new EmployeeProfileData(
            summary: EmployeeSummaryData::fromEmployee($employee),
            gender: $employee->gender,
            nationality: $employee->nationality,
            maritalStatus: $employee->marital_status,
            probationEndsOn: $employee->probation_ends_on?->toDateString(),
            terminationDate: $employee->termination_date?->toDateString(),
            terminationReason: $employee->termination_reason,
            employmentType: RefData::of($employee->currentEmploymentType),
            grade: RefData::of($employee->currentGrade),
            directReportsCount: $employee->directReports()->count(),
            account: [
                'linked' => $employee->user_id !== null,
                'email' => $employee->user?->email,
                'invitation' => $invitation === null ? null : ['email' => $invitation->email, 'expiresAt' => $invitation->expires_at->toIso8601String()],
            ],
            sensitive: $sensitive,
            customFields: $customFields,
        );
    }

    public function access(User $viewer, string $fieldKey): ?FieldAccess
    {
        return $this->resolver->fieldAccess($viewer, $fieldKey);
    }

    /**
     * @param  list<string>  $shown
     */
    private function sensitive(User $viewer, Employee $employee, array &$shown): SensitiveDetailsData
    {
        $access = [];
        $can = function (string $key) use ($viewer, &$access, &$shown, $employee): bool {
            $level = $this->access($viewer, $key);

            if (! $level instanceof FieldAccess) {
                return false;
            }

            $access[$key] = $level->value;

            if ($this->hasValue($employee, $key)) {
                $shown[] = $key;
            }

            return true;
        };

        $data = new SensitiveDetailsData(
            access: [],
            nationalId: $can('employee.national_id') ? $employee->national_id : null,
            passport: $can('employee.passport') ? [
                'number' => $employee->passport_number,
                'country' => $employee->passport_country,
                'expiresOn' => $employee->passport_expires_on?->toDateString(),
            ] : null,
            residency: $can('employee.residency') ? [
                'number' => $employee->residency_number,
                'expiresOn' => $employee->residency_expires_on?->toDateString(),
            ] : null,
            bank: $can('employee.bank') ? $employee->bank_details : null,
            personalContact: $can('employee.personal_contact') ? $employee->personal_contact : null,
            dateOfBirth: $can('employee.date_of_birth') ? $employee->date_of_birth?->toDateString() : null,
        );
        $data->access = $access;

        return $data;
    }

    /**
     * @param  list<string>  $shown
     * @return list<CustomFieldValueData>
     */
    private function customFields(User $viewer, Employee $employee, array &$shown): array
    {
        $values = $employee->customValues->keyBy('definition_id');
        $fields = [];

        foreach (CustomFieldDefinition::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $definition) {
            $value = $values->get($definition->id);
            $access = 'write';

            if ($definition->is_sensitive) {
                $level = $this->access($viewer, $definition->fieldKey());
                $access = $level instanceof FieldAccess ? $level->value : 'hidden';
            }

            if ($definition->is_sensitive && $access !== 'hidden' && $value instanceof EmployeeCustomValue) {
                $shown[] = $definition->fieldKey();
            }

            /** @var array<string, string> $label */
            $label = $definition->getTranslations('label');
            /** @var array<string, string> $help */
            $help = $definition->getTranslations('help');

            $fields[] = new CustomFieldValueData(
                key: $definition->key,
                label: $label,
                help: $help,
                type: $definition->type->value,
                section: $definition->section,
                options: $definition->options ?? [],
                isRequired: $definition->is_required,
                isSensitive: $definition->is_sensitive,
                access: $access,
                value: $access === 'hidden' ? null : $value?->resolved(),
            );
        }

        return $fields;
    }

    private function hasValue(Employee $employee, string $key): bool
    {
        return match ($key) {
            'employee.national_id' => $employee->national_id !== null,
            'employee.passport' => $employee->passport_number !== null,
            'employee.residency' => $employee->residency_number !== null,
            'employee.bank' => $employee->bank_details !== null,
            'employee.personal_contact' => $employee->personal_contact !== null,
            'employee.date_of_birth' => $employee->date_of_birth !== null,
            default => false,
        };
    }
}
