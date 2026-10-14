<?php

declare(strict_types=1);

namespace App\Domain\Forms\Actions;

use App\Domain\Forms\Enums\VersionStatus;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormSchema;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Saves the builder's schema into the form's draft, creating the draft (from the published
 * version's number + 1) when there is none. Published versions are never edited.
 */
final readonly class SaveFormDraft
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<mixed>  $schema
     */
    public function handle(Actor $actor, Form $form, array $schema): FormVersion
    {
        $problems = FormSchema::problems($schema, $form->is_public);

        if ($problems !== []) {
            $details = [];

            foreach ($problems as $path => $messages) {
                $details["schema.{$path}"] = $messages;
            }

            throw new DomainRuleViolation('forms.schema_invalid', __('The form has problems to fix before it can be saved.'), $details);
        }

        return $this->db->transaction(function () use ($actor, $form, $schema): FormVersion {
            Form::query()->whereKey($form->id)->lockForUpdate()->first();

            /** @var FormVersion $draft */
            $draft = $form->draft()->first() ?? $form->versions()->create([
                'number' => $form->versions()->count() + 1,
                'status' => VersionStatus::Draft,
                'schema' => $schema,
            ]);

            $draft->forceFill(['schema' => $schema])->save();

            activity('forms')->causedBy($actor->user)->performedOn($form)->event('draft.saved')
                ->withProperties(['version' => $draft->number])->log('Form draft saved');

            return $draft;
        });
    }
}
