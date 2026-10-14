<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Jobs\CommitImportJob;
use App\Domain\People\Models\Import;
use App\Support\Errors\DomainRuleViolation;

/**
 * Step 4: after the dry run, apply the valid rows in the background.
 */
final readonly class StartImportCommit
{
    public function handle(Import $import): Import
    {
        $resumable = $import->status === ImportStatus::Failed && $import->validated_at !== null;

        if ($import->status !== ImportStatus::Validated && ! $resumable) {
            throw new DomainRuleViolation('people.import_not_ready', __('Check the file before importing it.'));
        }

        if ($import->valid_rows === 0) {
            throw new DomainRuleViolation('people.import_nothing_valid', __('There are no valid rows to import.'));
        }

        $import->forceFill(['status' => ImportStatus::Committing, 'failure' => null])->save();

        dispatch(new CommitImportJob($import->id));

        return $import;
    }
}
