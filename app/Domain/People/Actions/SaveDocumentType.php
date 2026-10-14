<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\People\Data\DocumentTypeInputData;
use App\Domain\People\Models\DocumentType;

final readonly class SaveDocumentType
{
    public function handle(DocumentTypeInputData $input, ?DocumentType $type = null): DocumentType
    {
        $type ??= new DocumentType;
        $code = ReferenceValues::code($input->code);
        ReferenceValues::assertCodeAvailable(DocumentType::class, $code, $type);

        $days = array_values(array_unique(array_map(intval(...), $input->reminderDays)));
        rsort($days);

        $type->setTranslations('name', ReferenceValues::translations($input->name));
        $type->fill([
            'code' => $code,
            'requires_expiry' => $input->requiresExpiry,
            'reminder_days' => $days,
            'is_active' => $input->isActive,
        ])->save();

        return $type;
    }
}
