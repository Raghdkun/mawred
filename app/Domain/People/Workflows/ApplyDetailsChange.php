<?php

declare(strict_types=1);

namespace App\Domain\People\Workflows;

use App\Domain\People\Actions\UpdateSensitiveDetails;
use App\Domain\People\Data\SensitiveDetailsInputData;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\StepContext;
use App\Support\Actors\Actor;
use RuntimeException;

/**
 * Applies an approved "my details" change (self-service profile edit through a workflow) to the
 * requester's record: personal contact and/or bank details, whichever the form collected.
 * Re-running writes the same values, so a retried step is harmless.
 */
final readonly class ApplyDetailsChange implements RecordUpdate
{
    public const array CONTACT_FIELDS = ['email' => 'personal_email', 'phone' => 'personal_phone', 'address' => 'address', 'emergencyName' => 'emergency_name', 'emergencyPhone' => 'emergency_phone', 'emergencyRelation' => 'emergency_relation'];

    public const array BANK_FIELDS = ['bankName' => 'bank_name', 'iban' => 'iban', 'accountName' => 'account_name'];

    public function __construct(private UpdateSensitiveDetails $update) {}

    public function key(): string
    {
        return 'people.details_change';
    }

    public function label(): array
    {
        return ['en' => 'Update the requester’s contact or bank details', 'ar' => 'تحديث بيانات الاتصال أو البنك لمقدّم الطلب'];
    }

    public function availableInPhase(): int
    {
        return 0;
    }

    public function apply(StepContext $context): void
    {
        $employee = $context->instance->requester_employee_id === null ? null : Employee::query()->find($context->instance->requester_employee_id);

        if (! $employee instanceof Employee) {
            throw new RuntimeException('The requester no longer has an employee record.');
        }

        $answers = is_array($context->instance->context['field'] ?? null) ? $context->instance->context['field'] : [];
        $groups = is_array($answers['change'] ?? null) ? $answers['change'] : [];
        $input = [];

        if (in_array('contact', $groups, true)) {
            $input['personalContact'] = $this->pick($answers, self::CONTACT_FIELDS, $employee->personal_contact ?? [], [
                'email' => 'email', 'phone' => 'phone', 'address' => 'address', 'emergencyName' => 'emergency_name', 'emergencyPhone' => 'emergency_phone', 'emergencyRelation' => 'emergency_relation',
            ]);
        }

        if (in_array('bank', $groups, true)) {
            $input['bank'] = $this->pick($answers, self::BANK_FIELDS, $employee->bank_details ?? [], ['bankName' => 'bank_name', 'iban' => 'iban', 'accountName' => 'account_name']);
        }

        if ($input !== []) {
            $this->update->handle(Actor::system('workflow:'.$context->step->ulid), $employee, SensitiveDetailsInputData::from($input));
        }
    }

    /**
     * New answers win; anything the form left blank keeps its current value.
     *
     * @param  array<mixed>  $answers
     * @param  array<string, string>  $fields  input key => form field key
     * @param  array<string, string|null>  $current  stored values
     * @param  array<string, string>  $stored  input key => stored key
     * @return array<string, string|null>
     */
    private function pick(array $answers, array $fields, array $current, array $stored): array
    {
        $values = [];

        foreach ($fields as $inputKey => $fieldKey) {
            $answer = $answers[$fieldKey] ?? null;
            $values[$inputKey] = is_string($answer) && trim($answer) !== '' ? trim($answer) : ($current[$stored[$inputKey]] ?? null);
        }

        return $values;
    }
}
