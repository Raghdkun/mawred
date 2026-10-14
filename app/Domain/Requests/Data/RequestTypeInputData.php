<?php

declare(strict_types=1);

namespace App\Domain\Requests\Data;

use App\Domain\Requests\Enums\RequestCategory;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/** The request type settings form: names, category, privacy and the form + workflow it uses. */
final class RequestTypeInputData extends Data
{
    /**
     * @param  array<string, string|null>  $name
     * @param  array<string, string|null>  $description
     */
    public function __construct(
        public string $code,
        public array $name,
        public array $description,
        public RequestCategory $category,
        public ?string $icon,
        public bool $isSensitive,
        public bool $isActive,
        public int $sortOrder,
        public string $formId,
        public string $workflowId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
            'description.en' => ['nullable', 'string', 'max:300'],
            'description.ar' => ['nullable', 'string', 'max:300'],
            'category' => ['required', Rule::enum(RequestCategory::class)],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
            'sortOrder' => ['required', 'integer', 'between:0,10000'],
            'formId' => ['required', 'string', 'exists:forms,ulid'],
            'workflowId' => ['required', 'string', 'exists:workflow_definitions,ulid'],
        ];
    }
}
