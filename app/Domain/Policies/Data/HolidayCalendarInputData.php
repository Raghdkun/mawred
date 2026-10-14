<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class HolidayCalendarInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     * @param  list<string>  $branchIds  branch ULIDs observing this calendar
     */
    public function __construct(
        public string $code,
        public array $name,
        public ?string $countryCode = null,
        public bool $isActive = true,
        public array $branchIds = [],
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
            'countryCode' => ['nullable', 'string', 'size:2', 'alpha'],
            'isActive' => ['boolean'],
            'branchIds' => ['array'],
            'branchIds.*' => ['string', 'exists:branches,ulid'],
        ];
    }
}
