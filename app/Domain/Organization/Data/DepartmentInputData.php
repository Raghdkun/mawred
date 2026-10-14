<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DepartmentInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     */
    public function __construct(
        public string $code,
        public array $name,
        public ?string $parentId = null,
        public ?string $costCenterId = null,
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
            'parentId' => ['nullable', 'string', 'exists:departments,ulid'],
            'costCenterId' => ['nullable', 'string', 'exists:cost_centers,ulid'],
            'isActive' => ['boolean'],
        ];
    }
}
