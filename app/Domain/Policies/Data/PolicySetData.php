<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyTargets;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row of the policy sets list (screen 16): where the set is used now or from a future date.
 */
#[TypeScript]
final class PolicySetData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     * @param  list<PolicyAssignmentData>  $assignments
     */
    public function __construct(
        public string $id,
        public array $name,
        public array $description,
        public ?string $presetKey,
        public ?string $presetVersion,
        public int $keysCount,
        public ?string $lastChangeOn,
        public array $assignments,
    ) {}

    /**
     * Expects `assignments` (current and future, with `set`) and `values_count` / `values_max_effective_from` loaded.
     */
    public static function fromSet(PolicySet $set, PolicyTargets $targets): self
    {
        /** @var array<string, string> $name */
        $name = $set->getTranslations('name');
        /** @var array<string, string> $description */
        $description = $set->getTranslations('description');
        $lastChange = $set->getAttribute('values_max_effective_from');
        $keysCount = $set->getAttribute('keys_count');

        return new self(
            id: $set->ulid,
            name: $name,
            description: $description,
            presetKey: $set->preset_key,
            presetVersion: $set->preset_version,
            keysCount: is_numeric($keysCount) ? (int) $keysCount : 0,
            lastChangeOn: is_string($lastChange) ? substr($lastChange, 0, 10) : null,
            assignments: array_values($set->assignments->map(fn (PolicyAssignment $a): PolicyAssignmentData => PolicyAssignmentData::fromAssignment($a, $targets))->all()),
        );
    }
}
