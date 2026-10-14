<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use App\Domain\Organization\Models\Department;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One node of the department tree; the client builds the hierarchy from `parentId`.
 */
#[TypeScript]
final class DepartmentData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public ?string $parentId,
        public int $depth,
        public ?string $costCenterId,
        public bool $isActive,
    ) {}

    public static function fromDepartment(Department $department): self
    {
        /** @var array<string, string> $name */
        $name = $department->getTranslations('name');

        return new self(
            id: $department->ulid,
            code: $department->code,
            name: $name,
            parentId: $department->parent?->ulid,
            depth: $department->depth,
            costCenterId: $department->costCenter?->ulid,
            isActive: $department->is_active,
        );
    }
}
