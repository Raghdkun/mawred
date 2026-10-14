<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentTypeInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     * @param  list<int>  $reminderDays
     */
    public function __construct(
        public string $code,
        public array $name,
        public bool $requiresExpiry = false,
        public array $reminderDays = [30, 7],
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
            'requiresExpiry' => ['boolean'],
            'reminderDays' => ['present', 'array', 'max:5'],
            'reminderDays.*' => ['integer', 'between:0,365', 'distinct'],
            'isActive' => ['boolean'],
        ];
    }
}
