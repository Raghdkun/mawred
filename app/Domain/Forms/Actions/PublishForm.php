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
 * Publishes the draft: it becomes the version people fill in and is frozen. The previous
 * published version is archived; submissions made against it keep pointing at it.
 */
final readonly class PublishForm
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, Form $form): FormVersion
    {
        return $this->db->transaction(function () use ($actor, $form): FormVersion {
            Form::query()->whereKey($form->id)->lockForUpdate()->first();
            $draft = $form->draft()->first();

            if (! $draft instanceof FormVersion) {
                throw new DomainRuleViolation('forms.no_draft', __('There are no changes to publish.'));
            }

            if (FormSchema::fields($draft->schema) === []) {
                throw new DomainRuleViolation('forms.empty', __('Add at least one field before publishing.'));
            }

            if (FormSchema::problems($draft->schema, $form->is_public) !== []) {
                throw new DomainRuleViolation('forms.schema_invalid', __('The form has problems to fix before it can be saved.'));
            }

            $form->versions()->where('status', VersionStatus::Published->value)->update(['status' => VersionStatus::Archived->value]);
            $draft->forceFill(['status' => VersionStatus::Published, 'published_at' => now(), 'published_by' => $actor->user?->id])->save();
            $form->forceFill(['published_version_id' => $draft->id])->save();

            activity('forms')->causedBy($actor->user)->performedOn($form)->event('published')
                ->withProperties(['version' => $draft->number])->log('Form published');

            return $draft;
        });
    }
}
