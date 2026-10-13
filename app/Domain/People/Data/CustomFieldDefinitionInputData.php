<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Enums\CustomFieldType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CustomFieldDefinitionInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $label
     * @param  array{en?: string|null, ar?: string|null}|null  $help
     * @param  list<array{value: string, label: array{en: string, ar?: string|null}}>|null  $options
     */
    public function __construct(
        public string $key,
        public array $label,
        public string $type,
        public ?array $help = null,
        public ?array $options = null,
        public string $section = 'personal',
        public bool $isRequired = false,
        public bool $isSensitive = false,
        public bool $isActive = true,
        public int $sortOrder = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:48', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'array'],
            'label.en' => ['required', 'string', 'max:80'],
            'label.ar' => ['nullable', 'string', 'max:80'],
            'help' => ['nullable', 'array'],
            'help.en' => ['nullable', 'string', 'max:200'],
            'help.ar' => ['nullable', 'string', 'max:200'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['nullable', 'array', 'max:100'],
            'options.*.value' => ['required', 'string', 'max:60', 'distinct'],
            'options.*.label.en' => ['required', 'string', 'max:80'],
            'options.*.label.ar' => ['nullable', 'string', 'max:80'],
            'section' => ['required', Rule::in(['personal', 'job'])],
            'isRequired' => ['boolean'],
            'isSensitive' => ['boolean'],
            'isActive' => ['boolean'],
            'sortOrder' => ['integer', 'between:0,10000'],
        ];
    }
}
