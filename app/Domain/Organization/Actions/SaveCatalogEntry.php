<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Data\CatalogEntryInputData;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Support\ReferenceValues;
use InvalidArgumentException;

/**
 * Creates or edits an employment type, grade or cost centre.
 */
final readonly class SaveCatalogEntry
{
    public function handle(CatalogKind $kind, CatalogEntryInputData $input, EmploymentType|Grade|CostCenter|null $entry = null): EmploymentType|Grade|CostCenter
    {
        $entry ??= $kind->newModel();

        if (! $entry instanceof ($kind->modelClass())) {
            throw new InvalidArgumentException("Expected a {$kind->value} entry.");
        }

        $code = ReferenceValues::code($input->code);
        ReferenceValues::assertCodeAvailable($kind->modelClass(), $code, $entry);

        $entry->setTranslations('name', ReferenceValues::translations($input->name));
        $entry->fill(['code' => $code, 'is_active' => $input->isActive]);

        if ($kind->hasRank()) {
            $entry->setAttribute('rank', $input->rank ?? 0);
        }

        $entry->save();

        return $entry;
    }
}
