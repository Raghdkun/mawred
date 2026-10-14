<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyTargets;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PolicyAssignmentData extends Data
{
    /**
     * @param  array<string, string>  $setName
     */
    public function __construct(
        public string $id,
        public string $level,
        public ?string $targetId,
        public ?string $targetName,
        public string $setId,
        public array $setName,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public ?string $reason,
    ) {}

    public static function fromAssignment(PolicyAssignment $assignment, PolicyTargets $targets): self
    {
        $set = $assignment->set ?? PolicySet::withTrashed()->findOrFail($assignment->policy_set_id);
        /** @var array<string, string> $setName */
        $setName = $set->getTranslations('name');

        return new self(
            id: $assignment->ulid,
            level: $assignment->level->value,
            targetId: $targets->ulidFor($assignment->level, $assignment->target_id),
            targetName: $targets->name($assignment->level, $assignment->target_id),
            setId: $set->ulid,
            setName: $setName,
            effectiveFrom: $assignment->effective_from->toDateString(),
            effectiveTo: $assignment->effective_to?->toDateString(),
            reason: $assignment->reason,
        );
    }
}
