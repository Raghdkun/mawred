<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Forms\Models\Form;
use App\Domain\Requests\Data\RequestTypeInputData;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Creates or edits a request type (handoff 10 admin). The code is fixed once created; the phase
 * flag (D55) stays as seeded so a type can't go live before its module.
 */
final readonly class SaveRequestType
{
    public function handle(Actor $actor, RequestTypeInputData $data, ?RequestType $type = null): RequestType
    {
        $type ??= new RequestType;
        $code = $type->exists ? $type->code : mb_strtoupper($data->code);

        if (! $type->exists && RequestType::withTrashed()->where('code', $code)->exists()) {
            throw new DomainRuleViolation('requests.code_taken', __('That code is already used.'), ['code' => [__('That code is already used.')]]);
        }

        $type->fill([
            'code' => $code,
            'name' => array_filter($data->name, fn (?string $value): bool => $value !== null && trim($value) !== ''),
            'description' => array_filter($data->description, fn (?string $value): bool => $value !== null && trim($value) !== ''),
            'category' => $data->category,
            'icon' => $data->icon,
            'is_sensitive' => $data->isSensitive,
            'is_active' => $data->isActive,
            'sort_order' => $data->sortOrder,
            'form_id' => Form::query()->where('ulid', $data->formId)->value('id'),
            'workflow_definition_id' => WorkflowDefinition::query()->where('ulid', $data->workflowId)->value('id'),
        ])->save();

        return $type;
    }
}
