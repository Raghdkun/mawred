<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Models\CustomFieldDefinition;

/**
 * Archives a custom field. Stored values are kept (history, audit) but no longer shown or edited.
 */
final readonly class DeleteCustomFieldDefinition
{
    public function handle(CustomFieldDefinition $definition): void
    {
        $definition->delete();
    }
}
