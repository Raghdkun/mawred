<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Models\EmployeeDocument;

/**
 * Archives a document. The file is kept with the archived row until retention rules purge it.
 */
final readonly class DeleteEmployeeDocument
{
    public function handle(EmployeeDocument $document): void
    {
        $document->delete();
    }
}
