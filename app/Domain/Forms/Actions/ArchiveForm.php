<?php

declare(strict_types=1);

namespace App\Domain\Forms\Actions;

use App\Domain\Forms\Models\Form;

/**
 * Archives a form: it disappears from lists and its public link stops working. Submissions
 * and versions stay for the record.
 */
final readonly class ArchiveForm
{
    public function handle(Form $form): void
    {
        $form->forceFill(['is_public' => false])->save();
        $form->delete();
    }
}
