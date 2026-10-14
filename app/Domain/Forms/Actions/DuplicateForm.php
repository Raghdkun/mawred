<?php

declare(strict_types=1);

namespace App\Domain\Forms\Actions;

use App\Domain\Forms\Data\FormSettingsData;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Copies a form's latest schema (draft, else published) into a new, unpublished, internal form.
 */
final readonly class DuplicateForm
{
    public function __construct(private ConnectionInterface $db, private SaveFormSettings $settings) {}

    /**
     * @param  array{en: string, ar?: string|null}  $name
     */
    public function handle(Actor $actor, Form $source, string $code, array $name): Form
    {
        return $this->db->transaction(function () use ($actor, $source, $code, $name): Form {
            $form = $this->settings->handle($actor, new FormSettingsData(code: $code, name: $name, category: $source->category));
            $latest = $source->draft()->first() ?? $source->publishedVersion()->first();

            if ($latest instanceof FormVersion) {
                $form->versions()->update(['schema' => $latest->getRawOriginal('schema')]);
            }

            return $form;
        });
    }
}
