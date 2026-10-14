<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;

final readonly class DeleteCatalogEntry
{
    public function handle(EmploymentType|Grade|CostCenter $entry): void
    {
        $entry->delete();
    }
}
