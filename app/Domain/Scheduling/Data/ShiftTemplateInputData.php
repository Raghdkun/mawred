<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ShiftTemplateInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     */
    public function __construct(
        public string $code,
        public array $name,
        public string $startsAt,
        public string $endsAt,
        public int $breakMinutes = 0,
        public int $color = 1,
        public ?string $branchId = null,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:80'],
            'name.ar' => ['nullable', 'string', 'max:80'],
            'startsAt' => ['required', 'date_format:H:i'],
            'endsAt' => ['required', 'date_format:H:i', 'different:startsAt'],
            'breakMinutes' => ['integer', 'between:0,480'],
            'color' => ['integer', 'between:1,8'],
            'branchId' => ['nullable', 'string', 'size:26', 'exists:branches,ulid'],
            'isActive' => ['boolean'],
        ];
    }
}
