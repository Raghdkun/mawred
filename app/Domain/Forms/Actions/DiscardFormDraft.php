<?php

declare(strict_types=1);

namespace App\Domain\Forms\Actions;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Support\FormSchema;

/**
 * Throws away unpublished changes. A form that was never published keeps an empty draft.
 */
final readonly class DiscardFormDraft
{
    public function handle(Form $form): void
    {
        $draft = $form->draft()->first();

        if ($draft === null) {
            return;
        }

        if ($form->published_version_id === null) {
            $draft->forceFill(['schema' => FormSchema::blank()])->save();

            return;
        }

        $draft->delete();
    }
}
