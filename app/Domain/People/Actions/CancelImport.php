<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Models\Import;
use App\Support\Errors\DomainRuleViolation;

/**
 * Drops an import before it is committed (its rows and files go with it).
 */
final readonly class CancelImport
{
    public function handle(Import $import): void
    {
        if (! in_array($import->status, [ImportStatus::Uploaded, ImportStatus::Validated, ImportStatus::Failed], true)) {
            throw new DomainRuleViolation('people.import_busy', __('This import can no longer be changed.'));
        }

        $import->rows()->delete();
        $import->clearMediaCollection('source');
        $import->clearMediaCollection('error_report');
        $import->forceFill(['status' => ImportStatus::Cancelled])->save();
    }
}
