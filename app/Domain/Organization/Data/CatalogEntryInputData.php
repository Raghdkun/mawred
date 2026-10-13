<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An employment type, grade or cost centre. `rank` only applies to grades (higher = more senior).
 */
#[TypeScript]
final class CatalogEntryInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     */
    public function __construct(
        public string $code,
        public array $name,
        public ?int $rank = null,
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
            'rank' => ['nullable', 'integer', 'between:0,1000'],
            'isActive' => ['boolean'],
        ];
    }
}
