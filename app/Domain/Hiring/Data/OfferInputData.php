<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The terms of an offer and its letter text (D95); an empty letter is filled from the template. */
#[TypeScript]
final class OfferInputData extends Data
{
    /**
     * @param  array{amount_minor: int, currency: string}  $salary
     */
    public function __construct(
        public string $jobTitle,
        public string $startOn,
        public array $salary,
        public string $branchId,
        public ?string $departmentId = null,
        public ?string $positionId = null,
        public ?string $employmentTypeId = null,
        public ?string $managerId = null,
        public ?string $expiresOn = null,
        public ?string $letter = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'jobTitle' => ['required', 'string', 'max:160'],
            'startOn' => ['required', 'date_format:Y-m-d'],
            'salary' => ['required', 'array'],
            'salary.amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'salary.currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'branchId' => ['required', 'string', 'size:26', 'exists:branches,ulid'],
            'departmentId' => ['nullable', 'string', 'size:26', 'exists:departments,ulid'],
            'positionId' => ['nullable', 'string', 'size:26', 'exists:positions,ulid'],
            'employmentTypeId' => ['nullable', 'string', 'size:26', 'exists:employment_types,ulid'],
            'managerId' => ['nullable', 'string', 'size:26', 'exists:employees,ulid'],
            'expiresOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'letter' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
