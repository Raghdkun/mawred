<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\EmployeeAssignment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssignmentData extends Data
{
    public function __construct(
        public string $id,
        public bool $isPrimary,
        public bool $isCurrent,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public ?RefData $branch,
        public ?RefData $department,
        public ?RefData $position,
        public ?PersonRefData $manager,
        public ?RefData $employmentType,
        public ?RefData $grade,
        public ?string $reason,
    ) {}

    public static function fromAssignment(EmployeeAssignment $assignment): self
    {
        return new self(
            id: $assignment->ulid,
            isPrimary: $assignment->is_primary,
            isCurrent: $assignment->is_current,
            effectiveFrom: $assignment->effective_from->toDateString(),
            effectiveTo: $assignment->effective_to?->toDateString(),
            branch: RefData::of($assignment->branch),
            department: RefData::of($assignment->department),
            position: RefData::of($assignment->position),
            manager: PersonRefData::of($assignment->manager),
            employmentType: RefData::of($assignment->employmentType),
            grade: RefData::of($assignment->grade),
            reason: $assignment->reason,
        );
    }
}
