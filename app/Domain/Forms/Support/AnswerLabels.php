<?php

declare(strict_types=1);

namespace App\Domain\Forms\Support;

use App\Domain\Forms\Enums\FieldType;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\LeaveType;

/**
 * Display names for directory picker answers (stored as ULIDs or leave type codes), so a
 * submission reads "Riyadh HQ" rather than an id. Archived records still resolve.
 */
final class AnswerLabels
{
    /**
     * @param  array<mixed>  $schema
     * @param  array<string, mixed>  $answers
     * @return array<string, string> field key => label
     */
    public static function for(array $schema, array $answers, string $locale): array
    {
        $labels = [];

        foreach (FormSchema::fieldsByKey($schema) as $key => $field) {
            $value = $answers[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $label = match (FieldSpec::type($field)) {
                FieldType::Employee => Employee::withTrashed()->where('ulid', $value)->first()?->displayName($locale),
                FieldType::Department => Department::withTrashed()->where('ulid', $value)->first()?->getTranslation('name', $locale),
                FieldType::Branch => Branch::withTrashed()->where('ulid', $value)->first()?->getTranslation('name', $locale),
                FieldType::LeaveType => LeaveType::withTrashed()->where('code', $value)->first()?->getTranslation('name', $locale),
                default => null,
            };

            if (is_string($label)) {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }
}
