<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\CustomFieldDefinition;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeCustomValue;
use App\Domain\People\Support\CustomFieldValues;
use App\Domain\People\Support\FieldGuard;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Writes custom field values for one employee (`key => value`). Unknown or archived keys are
 * refused; sensitive keys need `write` on `employee.custom.<key>`; every value is validated by
 * its type before anything is saved.
 */
final readonly class SetCustomFieldValues
{
    public function __construct(private FieldGuard $guard) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Actor $actor, Employee $employee, array $values): void
    {
        $definitions = CustomFieldDefinition::query()->where('is_active', true)->whereIn('key', array_keys($values))->get()->keyBy('key');
        $region = $employee->current_branch_id === null ? null : Branch::withTrashed()->whereKey($employee->current_branch_id)->value('phone_region');
        $errors = [];
        $normalised = [];

        foreach ($values as $key => $value) {
            $definition = $definitions->get($key);

            if (! $definition instanceof CustomFieldDefinition) {
                $errors["custom.{$key}"] = [__('This field does not exist.')];

                continue;
            }

            if ($definition->is_sensitive) {
                $this->guard->assertCanWrite($actor, $definition->fieldKey(), "custom.{$key}");
            }

            [$clean, $error] = CustomFieldValues::normalise($definition, $value, is_string($region) ? $region : null);

            if ($error !== null) {
                $errors["custom.{$key}"] = [$error];

                continue;
            }

            $normalised[$key] = [$definition, $clean];
        }

        if ($errors !== []) {
            throw new DomainRuleViolation('people.custom_values_invalid', __('Some fields need attention.'), $errors);
        }

        foreach ($normalised as [$definition, $clean]) {
            $record = EmployeeCustomValue::query()->firstOrNew(['employee_id' => $employee->id, 'definition_id' => $definition->id]);

            if ($clean === null) {
                if ($record->exists) {
                    $record->delete();
                }

                continue;
            }

            $record->fill($definition->is_sensitive ? ['value' => null, 'value_encrypted' => $clean] : ['value' => $clean, 'value_encrypted' => null])->save();
        }
    }
}
