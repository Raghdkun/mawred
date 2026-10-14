<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Models\PolicyValue;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Every assignment and value in force on one date, indexed for fast in-memory resolution of many
 * employees at once (impact previews resolve thousands).
 */
final class PolicySnapshot
{
    /** @var array<string, array<int, PolicyAssignment>> level => target id (0 = global) => assignment */
    public array $assignments = [];

    /** @var array<int, array<string, PolicyValue>> set id => key => value */
    public array $values = [];

    /** @var array<int, PolicySet> */
    public array $sets = [];

    public function __construct(public readonly string $date)
    {
        $assignments = PolicyAssignment::query()
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->get();

        foreach ($assignments as $assignment) {
            $this->assignments[$assignment->level->value][$assignment->target_id ?? 0] = $assignment;
        }

        $setIds = $assignments->pluck('policy_set_id')->unique()->values()->all();

        foreach (PolicySet::withTrashed()->whereIn('id', $setIds)->get() as $set) {
            $this->sets[$set->id] = $set;
        }

        $values = PolicyValue::query()
            ->whereIn('policy_set_id', $setIds)
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->get();

        foreach ($values as $value) {
            $this->values[$value->policy_set_id][$value->key] = $value;
        }
    }

    /**
     * Assignments that apply to a placement, most specific first.
     *
     * @return list<array{level: PolicyLevel, assignment: PolicyAssignment, target: int|null}>
     */
    public function chainFor(Placement $placement): array
    {
        $chain = [];
        $add = function (PolicyLevel $level, ?int $target) use (&$chain): void {
            $assignment = $this->assignments[$level->value][$target ?? 0] ?? null;

            if ($assignment instanceof PolicyAssignment && ($target !== null || ! $level->needsTarget())) {
                $chain[] = ['level' => $level, 'assignment' => $assignment, 'target' => $target];
            }
        };

        $add(PolicyLevel::Employee, $placement->employeeId);
        $add(PolicyLevel::EmploymentType, $placement->employmentTypeId);
        $add(PolicyLevel::Grade, $placement->gradeId);
        $add(PolicyLevel::Position, $placement->positionId);

        foreach (array_reverse($placement->departmentIds) as $departmentId) {
            $add(PolicyLevel::Department, $departmentId);
        }

        $add(PolicyLevel::Branch, $placement->branchId);
        $add(PolicyLevel::Global, null);

        return $chain;
    }
}
