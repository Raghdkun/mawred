<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PositionInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $title
     */
    public function __construct(
        public string $code,
        public array $title,
        public ?string $departmentId = null,
        public ?string $gradeId = null,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:120'],
            'title.ar' => ['nullable', 'string', 'max:120'],
            'departmentId' => ['nullable', 'string', 'exists:departments,ulid'],
            'gradeId' => ['nullable', 'string', 'exists:grades,ulid'],
            'isActive' => ['boolean'],
        ];
    }
}
