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
     * A copy with some of a set's values replaced from the snapshot date (null removes a key), for
     * "what if" previews. Nothing is saved.
     *
     * @param  array<string, array<string, mixed>|null>  $values
     */
    public function withValues(PolicySet $set, array $values): self
    {
        $copy = $this->withSet($set);

        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($copy->values[$set->id][$key]);
            } else {
                $copy->values[$set->id][$key] = new PolicyValue(['policy_set_id' => $set->id, 'key' => $key, 'value' => $value, 'effective_from' => $this->date]);
            }
        }

        return $copy;
    }

    /**
     * A copy where a set is assigned to a target from the snapshot date (null set = unassigned).
     */
    public function withAssignment(PolicyLevel $level, ?int $targetId, ?PolicySet $set): self
    {
        $copy = $set instanceof PolicySet ? $this->withSet($set) : clone $this;
        $target = $level->needsTarget() ? (int) $targetId : 0;

        if ($set instanceof PolicySet) {
            $copy->assignments[$level->value][$target] = new PolicyAssignment([
                'policy_set_id' => $set->id, 'level' => $level, 'target_id' => $level->needsTarget() ? $targetId : null, 'effective_from' => $this->date,
            ]);
        } else {
            unset($copy->assignments[$level->value][$target]);
        }

        return $copy;
    }

    /**
     * Where a set is assigned in this snapshot.
     *
     * @return list<array{level: PolicyLevel, target: int|null}>
     */
    public function targetsOf(PolicySet $set): array
    {
        $targets = [];

        foreach ($this->assignments as $assignments) {
            foreach ($assignments as $assignment) {
                if ($assignment->policy_set_id === $set->id) {
                    $targets[] = ['level' => $assignment->level, 'target' => $assignment->target_id];
                }
            }
        }

        return $targets;
    }

    private function withSet(PolicySet $set): self
    {
        $copy = clone $this;

        if (! isset($copy->sets[$set->id])) {
            $copy->sets[$set->id] = $set;
            $copy->values[$set->id] = [];

            $values = PolicyValue::query()
                ->where('policy_set_id', $set->id)
                ->where('effective_from', '<=', $this->date)
                ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $this->date))
                ->get();

            foreach ($values as $value) {
                $copy->values[$set->id][$value->key] = $value;
            }
        }

        return $copy;
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
