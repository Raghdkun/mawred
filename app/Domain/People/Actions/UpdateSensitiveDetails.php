<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Data\SensitiveDetailsInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Support\EmployeeContact;
use App\Domain\People\Support\FieldGuard;
use App\Support\Actors\Actor;
use Spatie\LaravelData\Optional;

/**
 * Writes sensitive groups the actor holds `write` on (ADR-0007). Sending a group without the
 * permission is refused outright rather than silently dropped, so the UI never lies about a save.
 */
final readonly class UpdateSensitiveDetails
{
    public function __construct(private FieldGuard $guard) {}

    public function handle(Actor $actor, Employee $employee, SensitiveDetailsInputData $data): Employee
    {
        if (is_array($data->nationalId)) {
            $this->guard->assertCanWrite($actor, 'employee.national_id', 'nationalId');
            $employee->national_id = $this->clean($data->nationalId['number'] ?? null);
        }

        if (is_array($data->passport)) {
            $this->guard->assertCanWrite($actor, 'employee.passport', 'passport');
            $employee->passport_number = $this->clean($data->passport['number'] ?? null);
            $employee->passport_country = isset($data->passport['country']) ? mb_strtoupper($data->passport['country']) : null;
            $employee->setAttribute('passport_expires_on', $data->passport['expiresOn'] ?? null);
        }

        if (is_array($data->residency)) {
            $this->guard->assertCanWrite($actor, 'employee.residency', 'residency');
            $employee->residency_number = $this->clean($data->residency['number'] ?? null);
            $employee->setAttribute('residency_expires_on', $data->residency['expiresOn'] ?? null);
        }

        if (is_array($data->bank)) {
            $this->guard->assertCanWrite($actor, 'employee.bank', 'bank');
            $iban = isset($data->bank['iban']) ? mb_strtoupper((string) preg_replace('/\s+/', '', $data->bank['iban'])) : null;
            $employee->bank_details = $this->compact([
                'bank_name' => $data->bank['bankName'] ?? null,
                'iban' => $iban,
                'account_name' => $data->bank['accountName'] ?? null,
            ]);
        }

        if (is_array($data->personalContact)) {
            $this->guard->assertCanWrite($actor, 'employee.personal_contact', 'personalContact');
            $region = $employee->current_branch_id === null ? null : Branch::withTrashed()->whereKey($employee->current_branch_id)->value('phone_region');
            $region = is_string($region) ? $region : null;
            $contact = $data->personalContact;

            $employee->personal_contact = $this->compact([
                'email' => isset($contact['email']) ? mb_strtolower(trim($contact['email'])) : null,
                'phone' => EmployeeContact::phone($contact['phone'] ?? null, $region, 'personalContact.phone'),
                'address' => $contact['address'] ?? null,
                'emergency_name' => $contact['emergencyName'] ?? null,
                'emergency_phone' => EmployeeContact::phone($contact['emergencyPhone'] ?? null, $region, 'personalContact.emergencyPhone'),
                'emergency_relation' => $contact['emergencyRelation'] ?? null,
            ]);
        }

        if (! $data->dateOfBirth instanceof Optional) {
            $this->guard->assertCanWrite($actor, 'employee.date_of_birth', 'dateOfBirth');
            $employee->setAttribute('date_of_birth', $data->dateOfBirth);
        }

        $employee->save();

        return $employee;
    }

    private function clean(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string>|null
     */
    private function compact(array $values): ?array
    {
        $kept = array_filter($values, fn (?string $value): bool => $value !== null && trim($value) !== '');

        return $kept === [] ? null : $kept;
    }
}
