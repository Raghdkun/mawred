<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Organization\Models\Department;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\ImpactBalanceData;
use App\Domain\Policies\Data\ImpactChangeData;
use App\Domain\Policies\Data\ImpactPreviewData;
use App\Domain\Policies\Data\ImpactRowData;
use App\Domain\Policies\Data\Values\LeaveRuleValue;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Keys\LeaveRuleKey;
use App\Domain\Policies\Keys\PolicyKey;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\Calculators\AccrualCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Previews a policy change before it is saved: resolves the employees it could reach against the
 * current snapshot and a modified copy, and lists those whose values differ. Nothing is written.
 */
final readonly class PolicyImpact
{
    public const int ROWS = 50;

    public function __construct(private PolicyResolver $resolver) {}

    /**
     * Changing some of a set's values from a date.
     *
     * @param  array<string, array<string, mixed>|null>  $values  validated, serialised values
     */
    public function ofValues(PolicySet $set, array $values, string $date): ImpactPreviewData
    {
        $before = $this->resolver->snapshot($date);

        return $this->diff($before, $before->withValues($set, $values), $before->targetsOf($set), array_keys($values), $date);
    }

    /**
     * Assigning a set to a target from a date (replacing whatever is assigned there), or ending
     * the assignment when the set is null.
     */
    public function ofAssignment(PolicyLevel $level, ?int $targetId, ?PolicySet $set, string $date): ImpactPreviewData
    {
        $before = $this->resolver->snapshot($date);
        $after = $before->withAssignment($level, $targetId, $set);
        $current = $before->assignments[$level->value][$level->needsTarget() ? (int) $targetId : 0] ?? null;
        $keys = array_keys(array_replace(
            $current === null ? [] : $before->values[$current->policy_set_id] ?? [],
            $set instanceof PolicySet ? $after->values[$set->id] ?? [] : [],
        ));

        return $this->diff($before, $after, [['level' => $level, 'target' => $targetId]], array_map(strval(...), $keys), $date);
    }

    /**
     * @param  list<array{level: PolicyLevel, target: int|null}>  $targets
     * @param  list<string>  $keyIds
     */
    private function diff(PolicySnapshot $before, PolicySnapshot $after, array $targets, array $keyIds, string $date): ImpactPreviewData
    {
        $keys = array_map($this->resolver->key(...), $keyIds);
        $checked = 0;
        $affected = 0;
        $rows = [];

        if ($targets !== [] && $keys !== []) {
            $this->candidates($targets)->orderBy('name_en')->orderBy('id')->chunk(500, function (Collection $employees) use ($before, $after, $keys, $date, &$checked, &$affected, &$rows): void {
                $placements = $this->resolver->placementsFor($employees, $date);

                foreach ($employees as $employee) {
                    $checked++;
                    $changes = [];

                    foreach ($keys as $key) {
                        $old = $this->resolver->resolve($placements[$employee->id], $date, $key, $before);
                        $new = $this->resolver->resolve($placements[$employee->id], $date, $key, $after);

                        if ($old->value !== $new->value) {
                            $changes[] = new ImpactChangeData($key->id(), $old->value, $new->value, $old->level, $new->level);
                        }
                    }

                    if ($changes === []) {
                        continue;
                    }

                    $affected++;

                    if (count($rows) < self::ROWS) {
                        $rows[] = new ImpactRowData($employee->ulid, $employee->employee_number, $employee->name_en, $employee->name_ar, $changes, $this->balances($employee, $keys, $changes, $date));
                    }
                }
            });
        }

        return new ImpactPreviewData($date, $keyIds, $checked, $affected, $rows);
    }

    /**
     * For changed leave rules: what the employee earns over the change's (calendar, D63) year
     * under the current and the proposed rule.
     *
     * @param  list<PolicyKey>  $keys
     * @param  list<ImpactChangeData>  $changes
     * @return list<ImpactBalanceData>
     */
    private function balances(Employee $employee, array $keys, array $changes, string $date): array
    {
        $year = (int) substr($date, 0, 4);
        $balances = [];

        foreach ($changes as $change) {
            $key = array_values(array_filter($keys, fn (PolicyKey $k): bool => $k->id() === $change->key))[0] ?? null;

            if (! $key instanceof LeaveRuleKey) {
                continue;
            }

            $before = $this->earned($key, $employee, $year, $change->before);
            $after = $this->earned($key, $employee, $year, $change->after);
            $name = [];

            foreach ($key->type->getTranslations('name') as $locale => $text) {
                $name[(string) $locale] = is_string($text) ? $text : '';
            }

            $balances[] = new ImpactBalanceData($key->type->code, $name, $year, $before, $after, round($after - $before, 2));
        }

        return $balances;
    }

    /**
     * @param  array<string, mixed>  $value  a serialised leave rule
     */
    private function earned(LeaveRuleKey $key, Employee $employee, int $year, array $value): float
    {
        $rule = $key->hydrate($value);

        return $rule instanceof LeaveRuleValue
            ? AccrualCalculator::accrued($rule, $employee->hire_date->toDateString(), sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year), $employee->termination_date?->toDateString())->accrued
            : 0.0;
    }

    /**
     * Current employees placed under any of the targets (a department includes its subtree).
     *
     * @param  list<array{level: PolicyLevel, target: int|null}>  $targets
     * @return Builder<Employee>
     */
    private function candidates(array $targets): Builder
    {
        $query = Employee::query()->where('status', '!=', EmployeeStatus::Terminated);

        if (array_any($targets, fn (array $t): bool => $t['level'] === PolicyLevel::Global)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($targets): void {
            foreach ($targets as ['level' => $level, 'target' => $target]) {
                if ($level === PolicyLevel::Department) {
                    $root = Department::withTrashed()->find($target);

                    if ($root instanceof Department) {
                        $q->orWhereIn('current_department_id', Department::withTrashed()->withinSubtree($root)->select('id'));
                    }

                    continue;
                }

                $q->orWhere(match ($level) {
                    PolicyLevel::Employee => 'id',
                    PolicyLevel::EmploymentType => 'current_employment_type_id',
                    PolicyLevel::Grade => 'current_grade_id',
                    PolicyLevel::Position => 'current_position_id',
                    default => 'current_branch_id',
                }, $target);
            }
        });
    }
}
