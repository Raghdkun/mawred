<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where someone works: branch, department, position, manager, type, grade (all public ULIDs).
 */
#[TypeScript]
final class PlacementInputData extends Data
{
    public function __construct(
        public string $branchId,
        public ?string $departmentId = null,
        public ?string $positionId = null,
        public ?string $managerId = null,
        public ?string $employmentTypeId = null,
        public ?string $gradeId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'branchId' => ['required', 'string', 'exists:branches,ulid'],
            'departmentId' => ['nullable', 'string', 'exists:departments,ulid'],
            'positionId' => ['nullable', 'string', 'exists:positions,ulid'],
            'managerId' => ['nullable', 'string', 'exists:employees,ulid'],
            'employmentTypeId' => ['nullable', 'string', 'exists:employment_types,ulid'],
            'gradeId' => ['nullable', 'string', 'exists:grades,ulid'],
        ];
    }
}
