<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\Policies\Data\ChainLinkData;
use App\Domain\Policies\Data\ResolvedValueData;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Keys\PolicyKey;
use App\Domain\Policies\Models\PolicySet;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * Resolves policy keys for an employee on a local date (ADR-0010). Per key, the most specific
 * assignment that defines it wins: employee > employment type > grade (D13) > position >
 * department (nearest first) > branch > branch record (work week, D46) > global > key default.
 * Every answer carries its full chain for the explainer. Pure for a given snapshot.
 */
final class PolicyResolver
{
    /** @var array<string, PolicySnapshot> */
    private array $snapshots = [];

    /** @var array<int, Branch|null> */
    private array $branches = [];

    public function __construct(
        private readonly PolicyKeys $keys,
        private readonly PolicyEpoch $epoch,
        private readonly PolicyTargets $targets,
    ) {}

    public function snapshot(string $date): PolicySnapshot
    {
        return $this->snapshots[$this->epoch->current().'|'.$date] ??= new PolicySnapshot($date);
    }

    /**
     * @return array<string, ResolvedValueData> key id => resolved value, in editor order
     */
    public function all(Employee $employee, string $date): array
    {
        $placement = $this->placementOf($employee, $date);
        $resolved = [];

        foreach ($this->keys->all() as $id => $key) {
            $resolved[$id] = $this->resolve($placement, $date, $key);
        }

        return $resolved;
    }

    public function get(Employee $employee, string $date, string $keyId): ResolvedValueData
    {
        return $this->resolve($this->placementOf($employee, $date), $date, $this->key($keyId));
    }

    /**
     * The typed value, e.g. `$resolver->value($employee, $date, 'overtime.rules')` → OvertimeValue.
     */
    public function value(Employee $employee, string $date, string $keyId): Data
    {
        $key = $this->key($keyId);

        return $key->hydrate($this->get($employee, $date, $keyId)->value);
    }

    public function resolve(Placement $placement, string $date, PolicyKey $key, ?PolicySnapshot $snapshot = null): ResolvedValueData
    {
        $snapshot ??= $this->snapshot($date);
        $links = [];
        $branchDefaultAdded = false;

        foreach ($snapshot->chainFor($placement) as $entry) {
            if ($entry['level'] === PolicyLevel::Global && ! $branchDefaultAdded) {
                $links = [...$links, ...$this->branchDefault($placement, $key, ! $this->hasWinner($links))];
                $branchDefaultAdded = true;
            }

            $stored = $snapshot->values[$entry['assignment']->policy_set_id][$key->id()] ?? null;

            if ($stored === null) {
                continue;
            }

            $set = $snapshot->sets[$entry['assignment']->policy_set_id] ?? null;
            $links[] = new ChainLinkData(
                level: $entry['level']->value,
                setId: $set?->ulid,
                setName: $set instanceof PolicySet ? $this->names($set) : null,
                target: $this->targetName($entry['level'], $entry['target']),
                value: $key->serialize($key->hydrate($stored->value)),
                effectiveFrom: $stored->effective_from->toDateString(),
                wins: ! $this->hasWinner($links),
            );
        }

        if (! $branchDefaultAdded) {
            $links = [...$links, ...$this->branchDefault($placement, $key, ! $this->hasWinner($links))];
        }

        $links[] = new ChainLinkData('default', null, null, null, $key->serialize($key->default()), null, ! $this->hasWinner($links));

        $winner = array_values(array_filter($links, fn (ChainLinkData $link): bool => $link->wins))[0];

        return new ResolvedValueData(
            key: $key->id(),
            section: $key->section()->value,
            value: $winner->value,
            level: $winner->level,
            setId: $winner->setId,
            setName: $winner->setName,
            effectiveFrom: $winner->effectiveFrom,
            chain: $links,
        );
    }

    public function placementOf(Employee $employee, string $date): Placement
    {
        return $this->placementsFor(collect([$employee]), $date)[$employee->id];
    }

    /**
     * Placements for many employees in a few queries (impact previews).
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, Placement> employee id => placement
     */
    public function placementsFor(Collection $employees, string $date): array
    {
        $assignments = EmployeeAssignment::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('is_primary', true)
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->get()
            ->keyBy('employee_id');

        $placements = [];
        $departmentIds = [];

        foreach ($employees as $employee) {
            $assignment = $assignments->get($employee->id);
            $placements[$employee->id] = [
                'id' => $employee->id,
                'branch' => $assignment->branch_id ?? $employee->current_branch_id,
                'department' => $assignment->department_id ?? $employee->current_department_id,
                'position' => $assignment->position_id ?? $employee->current_position_id,
                'grade' => $assignment->grade_id ?? $employee->current_grade_id,
                'type' => $assignment->employment_type_id ?? $employee->current_employment_type_id,
            ];
            $departmentIds[] = $placements[$employee->id]['department'];
        }

        $paths = [];

        foreach (Department::withTrashed()->whereIn('id', array_filter(array_unique($departmentIds)))->get(['id', 'path']) as $department) {
            $paths[$department->id] = $department->path;
        }

        return array_map(fn (array $p): Placement => new Placement(
            employeeId: $p['id'],
            branchId: $p['branch'],
            departmentIds: $p['department'] === null ? [] : array_values(array_map(intval(...), array_filter(explode('.', $paths[$p['department']] ?? (string) $p['department']), fn (string $id): bool => $id !== ''))),
            positionId: $p['position'],
            gradeId: $p['grade'],
            employmentTypeId: $p['type'],
        ), $placements);
    }

    public function key(string $id): PolicyKey
    {
        return $this->keys->find($id) ?? throw new InvalidArgumentException("Unknown policy key [{$id}].");
    }

    /**
     * @param  list<ChainLinkData>  $links
     */
    private function hasWinner(array $links): bool
    {
        return array_any($links, fn (ChainLinkData $link): bool => $link->wins);
    }

    /**
     * @return list<ChainLinkData>
     */
    private function branchDefault(Placement $placement, PolicyKey $key, bool $wins): array
    {
        $branch = $placement->branchId === null ? null : ($this->branches[$placement->branchId] ??= Branch::withTrashed()->find($placement->branchId));

        if (! $branch instanceof Branch) {
            return [];
        }

        $value = $key->branchFallback($branch);

        if (! $value instanceof Data) {
            return [];
        }

        return [new ChainLinkData('branch_default', null, null, $this->targetName(PolicyLevel::Branch, $branch->id), $key->serialize($value), null, $wins)];
    }

    /**
     * @return array<string, string>
     */
    private function names(PolicySet $set): array
    {
        /** @var array<string, string> $names */
        $names = $set->getTranslations('name');

        return $names;
    }

    private function targetName(PolicyLevel $level, ?int $id): ?string
    {
        return $this->targets->name($level, $id);
    }
}
