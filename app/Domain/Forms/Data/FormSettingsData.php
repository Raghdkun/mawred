<?php

declare(strict_types=1);

namespace App\Domain\Forms\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A form's name and where it can be filled in. The code is fixed once created.
 */
#[TypeScript]
final class FormSettingsData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     * @param  array{en?: string|null, ar?: string|null}  $description
     * @param  array{en?: string|null, ar?: string|null}  $successMessage
     */
    public function __construct(
        public string $code,
        public array $name,
        public array $description = [],
        public ?string $category = null,
        public bool $isPublic = false,
        public ?string $publicSlug = null,
        public array $successMessage = [],
        public ?string $closesAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
            'description' => ['array'],
            'description.en' => ['nullable', 'string', 'max:500'],
            'description.ar' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:40'],
            'isPublic' => ['boolean'],
            'publicSlug' => ['nullable', 'required_if:isPublic,true', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'successMessage' => ['array'],
            'successMessage.en' => ['nullable', 'string', 'max:500'],
            'successMessage.ar' => ['nullable', 'string', 'max:500'],
            'closesAt' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
