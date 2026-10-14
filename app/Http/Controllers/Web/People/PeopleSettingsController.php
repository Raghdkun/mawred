<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Models\User;
use App\Domain\People\Actions\DeleteCustomFieldDefinition;
use App\Domain\People\Actions\SaveCustomFieldDefinition;
use App\Domain\People\Actions\SaveDocumentType;
use App\Domain\People\Actions\SaveNumberingSettings;
use App\Domain\People\Data\CustomFieldDefinitionInputData;
use App\Domain\People\Data\DocumentTypeInputData;
use App\Domain\People\Enums\CustomFieldType;
use App\Domain\People\Models\CustomFieldDefinition;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Support\EmployeeNumbers;
use App\Domain\Setup\Support\Settings;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * People settings: custom employee fields and document types (people.configure).
 */
final class PeopleSettingsController extends Controller
{
    public function fields(Settings $settings): Response
    {
        Gate::authorize('people.configure');

        return Inertia::render('people/settings/fields', [
            'fields' => CustomFieldDefinition::query()->orderBy('section')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (CustomFieldDefinition $field): array => [
                    'id' => $field->ulid,
                    'key' => $field->key,
                    'label' => $field->getTranslations('label'),
                    'help' => $field->getTranslations('help'),
                    'type' => $field->type->value,
                    'options' => $field->options ?? [],
                    'section' => $field->section,
                    'isRequired' => $field->is_required,
                    'isSensitive' => $field->is_sensitive,
                    'isActive' => $field->is_active,
                    'sortOrder' => $field->sort_order,
                ]),
            'types' => array_map(fn (CustomFieldType $type): string => $type->value, CustomFieldType::cases()),
            'numberPrefix' => $settings->get('people.number_prefix', EmployeeNumbers::DEFAULT_PREFIX),
        ]);
    }

    public function updateNumbering(Request $request, SaveNumberingSettings $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('people.configure');
        $request->validate(['prefix' => ['required', 'string', 'max:10']]);

        $save->handle(Actor::user($user), $request->string('prefix')->toString());

        return $this->saved();
    }

    public function storeField(CustomFieldDefinitionInputData $data, SaveCustomFieldDefinition $save): RedirectResponse
    {
        Gate::authorize('people.configure');

        $save->handle($data);

        return $this->saved();
    }

    public function updateField(CustomFieldDefinitionInputData $data, CustomFieldDefinition $field, SaveCustomFieldDefinition $save): RedirectResponse
    {
        Gate::authorize('people.configure');

        $save->handle($data, $field);

        return $this->saved();
    }

    public function destroyField(CustomFieldDefinition $field, DeleteCustomFieldDefinition $delete): RedirectResponse
    {
        Gate::authorize('people.configure');

        $delete->handle($field);

        return $this->saved(__('Archived.'));
    }

    public function documentTypes(): Response
    {
        Gate::authorize('people.configure');

        return Inertia::render('people/settings/document-types', [
            'types' => DocumentType::query()->orderBy('code')->get()->map(fn (DocumentType $type): array => [
                'id' => $type->ulid,
                'code' => $type->code,
                'name' => $type->getTranslations('name'),
                'requiresExpiry' => $type->requires_expiry,
                'reminderDays' => $type->reminder_days,
                'isActive' => $type->is_active,
            ]),
        ]);
    }

    public function storeDocumentType(DocumentTypeInputData $data, SaveDocumentType $save): RedirectResponse
    {
        Gate::authorize('people.configure');

        $save->handle($data);

        return $this->saved();
    }

    public function updateDocumentType(DocumentTypeInputData $data, DocumentType $type, SaveDocumentType $save): RedirectResponse
    {
        Gate::authorize('people.configure');

        $save->handle($data, $type);

        return $this->saved();
    }

    private function saved(?string $message = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message ?? __('Saved.')]);

        return back();
    }
}
