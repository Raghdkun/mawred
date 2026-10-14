<?php

declare(strict_types=1);

namespace App\Domain\Forms\Actions;

use App\Domain\Forms\Data\FormSettingsData;
use App\Domain\Forms\Enums\VersionStatus;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Forms\Support\FormSchema;
use App\Domain\Organization\Support\ReferenceValues;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates a form (with an empty draft) or edits its settings. A public link (D53) needs a unique
 * slug and a form without directory pickers.
 */
final readonly class SaveFormSettings
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, FormSettingsData $data, ?Form $form = null): Form
    {
        $form ??= new Form(['created_by' => $actor->user?->id]);
        $code = $form->exists ? $form->code : ReferenceValues::code($data->code);

        if (! $form->exists) {
            ReferenceValues::assertCodeAvailable(Form::class, $code);
        }

        $slug = $data->isPublic ? $data->publicSlug : null;

        if ($slug !== null && Form::withTrashed()->where('public_slug', $slug)->whereKeyNot($form->id)->exists()) {
            throw new DomainRuleViolation('forms.slug_taken', __('This link is already used by another form.'), ['publicSlug' => [__('This link is already used by another form.')]]);
        }

        if ($data->isPublic && $form->exists && $this->usesPickers($form)) {
            throw new DomainRuleViolation('forms.public_pickers', __('Public forms cannot use directory pickers.'), ['isPublic' => [__('Public forms cannot use directory pickers.')]]);
        }

        return $this->db->transaction(function () use ($form, $data, $code, $slug): Form {
            $form->setTranslations('name', ReferenceValues::translations($data->name));
            $form->setTranslations('description', ReferenceValues::translations($data->description));
            $form->fill([
                'code' => $code,
                'category' => $data->category,
                'is_public' => $data->isPublic,
                'public_slug' => $slug ?? $form->public_slug,
                'public_settings' => [
                    'successMessage' => array_filter(ReferenceValues::translations($data->successMessage), fn (?string $text): bool => $text !== null && $text !== ''),
                    'closesAt' => $data->closesAt,
                ],
            ])->save();

            if (! $form->versions()->exists()) {
                $form->versions()->create(['number' => 1, 'status' => VersionStatus::Draft, 'schema' => FormSchema::blank()]);
            }

            return $form;
        });
    }

    private function usesPickers(Form $form): bool
    {
        foreach ($form->versions()->whereIn('status', [VersionStatus::Draft->value, VersionStatus::Published->value])->get() as $version) {
            foreach (FormSchema::fields($version->schema) as $field) {
                if (FieldSpec::type($field)?->isPicker() === true) {
                    return true;
                }
            }
        }

        return false;
    }
}
