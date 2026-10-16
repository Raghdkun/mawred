<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BookingPageInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $title
     * @param  array{en?: string|null, ar?: string|null}|null  $description
     * @param  list<array{weekday: int, from: string, to: string}>  $windows
     */
    public function __construct(
        public array $title,
        public int $durationMinutes,
        public array $windows,
        public string $timezone,
        public string $startsOn,
        public ?string $endsOn = null,
        public int $bufferMinutes = 0,
        public ?array $description = null,
        public ?string $location = null,
        public string $audience = 'everyone',
        public ?string $branchId = null,
        public ?string $departmentId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:120'],
            'title.ar' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string', 'max:2000'],
            'description.ar' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:200'],
            'durationMinutes' => ['required', 'integer', Rule::in([15, 20, 30, 45, 60, 90, 120])],
            'bufferMinutes' => ['integer', 'between:0,120'],
            'windows' => ['required', 'array', 'min:1', 'max:21'],
            'windows.*.weekday' => ['required', 'integer', 'between:1,7'],
            'windows.*.from' => ['required', 'date_format:H:i'],
            'windows.*.to' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone:all'],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
            'audience' => ['required', Rule::in(['everyone', 'branch', 'department'])],
            'branchId' => ['nullable', 'required_if:audience,branch', 'string', 'size:26', 'exists:branches,ulid'],
            'departmentId' => ['nullable', 'required_if:audience,department', 'string', 'size:26', 'exists:departments,ulid'],
        ];
    }
}
