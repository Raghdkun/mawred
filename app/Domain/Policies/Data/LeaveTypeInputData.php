<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class LeaveTypeInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     */
    public function __construct(
        public string $code,
        public array $name,
        public string $unit = 'days',
        public bool $isPaid = true,
        public bool $allowsHalfDays = true,
        public ?string $eligibleGender = null,
        public bool $requiresAttachment = false,
        public int $sortOrder = 0,
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
            'unit' => ['required', Rule::in(['days', 'hours'])],
            'isPaid' => ['boolean'],
            'allowsHalfDays' => ['boolean'],
            'eligibleGender' => ['nullable', Rule::in(['female', 'male'])],
            'requiresAttachment' => ['boolean'],
            'sortOrder' => ['integer', 'between:0,10000'],
            'isActive' => ['boolean'],
        ];
    }
}
