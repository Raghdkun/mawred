<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Jobs\ValidateImportJob;
use App\Domain\People\Models\Import;
use App\Domain\People\Support\Imports\EmployeeImportFields;
use App\Support\Errors\DomainRuleViolation;

/**
 * Step 2: confirm which column feeds which field, then validate the whole file in the background
 * (a dry run: nothing is written to employees yet).
 */
final readonly class SaveImportMapping
{
    /**
     * @param  array<string, string|null>  $mapping  column index => field key (null = ignore)
     */
    public function handle(Import $import, array $mapping): Import
    {
        if (! in_array($import->status, [ImportStatus::Uploaded, ImportStatus::Validated, ImportStatus::Failed], true)) {
            throw new DomainRuleViolation('people.import_busy', __('This import can no longer be changed.'));
        }

        $fields = EmployeeImportFields::all();
        $clean = [];

        foreach ($mapping as $column => $field) {
            if ($field === null || $field === '') {
                continue;
            }

            if (! isset($fields[$field]) || ! array_key_exists((int) $column, $import->headers)) {
                throw new DomainRuleViolation('people.import_mapping_invalid', __('Unknown column or field.'), ['mapping' => [__('Unknown column or field.')]]);
            }

            if (in_array($field, $clean, true)) {
                throw new DomainRuleViolation('people.import_mapping_duplicate', __('Each field can only come from one column.'), ["mapping.{$column}" => [__('Each field can only come from one column.')]]);
            }

            $clean[(string) $column] = $field;
        }

        if (array_intersect(['employee_number', 'work_email', 'name_en'], $clean) === []) {
            throw new DomainRuleViolation('people.import_mapping_identifier', __('Map at least the employee number, work email or name.'), ['mapping' => [__('Map at least the employee number, work email or name.')]]);
        }

        $import->forceFill(['mapping' => $clean, 'status' => ImportStatus::Validating, 'failure' => null])->save();

        dispatch(new ValidateImportJob($import->id));

        return $import;
    }
}
