<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\FieldPermission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\AccessEpoch;
use App\Domain\People\Data\CustomFieldDefinitionInputData;
use App\Domain\People\Enums\CustomFieldType;
use App\Domain\People\Models\CustomFieldDefinition;
use App\Domain\People\Models\EmployeeCustomValue;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Creates or edits a custom employee field. The key is fixed once created; type and sensitivity
 * are fixed once values exist (changing them would silently reinterpret or expose stored data).
 * A new sensitive field is writable by the roles that hold `people.configure`; everyone else
 * starts with no access until HR grants it in the field matrix.
 */
final readonly class SaveCustomFieldDefinition
{
    public function __construct(private AccessEpoch $epoch) {}

    public function handle(CustomFieldDefinitionInputData $input, ?CustomFieldDefinition $definition = null): CustomFieldDefinition
    {
        $definition ??= new CustomFieldDefinition;
        $type = CustomFieldType::from($input->type);

        if (! $definition->exists && CustomFieldDefinition::withTrashed()->where('key', $input->key)->exists()) {
            throw new DomainRuleViolation('people.custom_key_taken', __('This key is already used.'), ['key' => [__('This key is already used.')]]);
        }

        if ($definition->exists && $input->key !== $definition->key) {
            throw new DomainRuleViolation('people.custom_key_fixed', __('The key cannot change after the field is created.'), ['key' => [__('The key cannot change after the field is created.')]]);
        }

        $hasValues = $definition->exists && EmployeeCustomValue::query()->where('definition_id', $definition->id)->exists();

        if ($hasValues && ($type !== $definition->type || $input->isSensitive !== $definition->is_sensitive)) {
            throw new DomainRuleViolation('people.custom_field_in_use', __('Type and sensitivity cannot change once employees have values.'), ['type' => [__('Type and sensitivity cannot change once employees have values.')]]);
        }

        if ($type->hasOptions() && ($input->options ?? []) === []) {
            throw new DomainRuleViolation('people.custom_options_required', __('Add at least one option.'), ['options' => [__('Add at least one option.')]]);
        }

        $becomesSensitive = $input->isSensitive && ! $definition->is_sensitive;

        $definition->setTranslations('label', array_filter($input->label, fn (?string $v): bool => $v !== null && trim($v) !== ''));
        $definition->setTranslations('help', array_filter($input->help ?? [], fn (?string $v): bool => $v !== null && trim($v) !== ''));
        $definition->fill([
            'key' => $input->key,
            'type' => $type,
            'options' => $type->hasOptions() ? array_map(fn (array $option): array => [
                'value' => $option['value'],
                'label' => array_filter($option['label'], fn (?string $v): bool => $v !== null && trim($v) !== ''),
            ], $input->options ?? []) : null,
            'section' => $input->section,
            'is_required' => $input->isRequired,
            'is_sensitive' => $input->isSensitive,
            'is_active' => $input->isActive,
            'sort_order' => $input->sortOrder,
        ])->save();

        if ($becomesSensitive) {
            $this->grantConfigurers($definition);
        }

        return $definition;
    }

    private function grantConfigurers(CustomFieldDefinition $definition): void
    {
        $roles = Role::query()->whereHas('permissions', fn (Builder $q) => $q->where('name', 'people.configure'))->pluck('id');

        // Composite key (role_id, field_key): upsert rather than Eloquent saves keyed on role_id.
        FieldPermission::query()->upsert(
            $roles->map(fn (mixed $roleId): array => ['role_id' => $roleId, 'field_key' => $definition->fieldKey(), 'access' => FieldAccess::Write->value])->all(),
            ['role_id', 'field_key'],
            ['access'],
        );

        $this->epoch->bump();
    }
}
