<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Hiring an accepted candidate (D85): details prefilled from the offer, confirmed by HR. */
#[TypeScript]
final class HireCandidateData extends Data
{
    public function __construct(
        public string $nameEn,
        public string $hireDate,
        public string $branchId,
        public ?string $nameAr = null,
        public ?string $departmentId = null,
        public ?string $positionId = null,
        public ?string $managerId = null,
        public ?string $employmentTypeId = null,
        public ?string $workEmail = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'nameEn' => ['required', 'string', 'max:160'],
            'nameAr' => ['nullable', 'string', 'max:160'],
            'hireDate' => ['required', 'date_format:Y-m-d'],
            'branchId' => ['required', 'string', 'size:26', 'exists:branches,ulid'],
            'departmentId' => ['nullable', 'string', 'size:26', 'exists:departments,ulid'],
            'positionId' => ['nullable', 'string', 'size:26', 'exists:positions,ulid'],
            'managerId' => ['nullable', 'string', 'size:26', 'exists:employees,ulid'],
            'employmentTypeId' => ['nullable', 'string', 'size:26', 'exists:employment_types,ulid'],
            'workEmail' => ['nullable', 'email', 'max:254'],
        ];
    }
}
