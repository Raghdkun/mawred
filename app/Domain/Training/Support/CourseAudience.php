<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who a mandatory course is for (D100): branches, departments (with everything under them),
 * positions and employment types, optionally only people hired in the last N days. Within one
 * list any value matches; across lists all must match; an empty list matches everyone. Stored
 * by ULID on the course and resolved to ids here, so the nightly run is one query.
 */
final readonly class CourseAudience
{
    /**
     * @param  list<string>  $branches
     * @param  list<string>  $departments
     * @param  list<string>  $positions
     * @param  list<string>  $employmentTypes
     */
    public function __construct(
        public array $branches = [],
        public array $departments = [],
        public array $positions = [],
        public array $employmentTypes = [],
        public ?int $hiredWithinDays = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function from(?array $stored): ?self
    {
        if ($stored === null) {
            return null;
        }

        $list = fn (string $key): array => array_values(array_filter(is_array($stored[$key] ?? null) ? $stored[$key] : [], is_string(...)));
        $days = $stored['hired_within_days'] ?? null;

        return new self($list('branches'), $list('departments'), $list('positions'), $list('employment_types'), is_int($days) && $days > 0 ? $days : null);
    }

    /**
     * Checks every ULID still exists, so a typo never silently widens the audience.
     */
    public function assertValid(): void
    {
        foreach ([
            'branches' => [Branch::class, $this->branches],
            'departments' => [Department::class, $this->departments],
            'positions' => [Position::class, $this->positions],
            'employment_types' => [EmploymentType::class, $this->employmentTypes],
        ] as $key => [$model, $ulids]) {
            /** @var class-string<Model> $model */
            if ($ulids !== [] && $model::query()->whereIn('ulid', $ulids)->count() !== count(array_unique($ulids))) {
                $message = __('Some of the people chosen for this course no longer exist. Pick them again.');

                throw new DomainRuleViolation('training.audience_unknown', $message, ["audience.{$key}" => [$message]]);
            }
        }
    }

    /**
     * @return array{branches: list<string>, departments: list<string>, positions: list<string>, employment_types: list<string>, hired_within_days: int|null}
     */
    public function toArray(): array
    {
        return [
            'branches' => $this->branches,
            'departments' => $this->departments,
            'positions' => $this->positions,
            'employment_types' => $this->employmentTypes,
            'hired_within_days' => $this->hiredWithinDays,
        ];
    }

    /**
     * Active and pending employees in the audience, as of `$today` (Y-m-d) for the new-hire window.
     *
     * @return Builder<Employee>
     */
    public function employees(string $today): Builder
    {
        $query = Employee::query()->whereIn('status', [EmployeeStatus::Active->value, EmployeeStatus::Pending->value]);

        if ($this->branches !== []) {
            $query->whereIn('current_branch_id', $this->ids(Branch::class, $this->branches));
        }

        if ($this->departments !== []) {
            $paths = Department::query()->whereIn('ulid', $this->departments)->pluck('path')->all();
            $query->whereIn('current_department_id', Department::query()->select('id')->where(function (Builder $q) use ($paths): void {
                foreach ($paths as $path) {
                    $q->orWhereRaw('path <@ ?::ltree', [$path]);
                }
            }));
        }

        if ($this->positions !== []) {
            $query->whereIn('current_position_id', $this->ids(Position::class, $this->positions));
        }

        if ($this->employmentTypes !== []) {
            $query->whereIn('current_employment_type_id', $this->ids(EmploymentType::class, $this->employmentTypes));
        }

        if ($this->hiredWithinDays !== null) {
            $query->where('hire_date', '>=', CarbonImmutable::parse($today)->subDays($this->hiredWithinDays)->toDateString());
        }

        return $query;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $ulids
     * @return list<int>
     */
    private function ids(string $model, array $ulids): array
    {
        $ids = [];

        foreach ($model::query()->whereIn('ulid', $ulids)->get(['id']) as $row) {
            $key = $row->getKey();

            if (is_int($key)) {
                $ids[] = $key;
            }
        }

        return $ids;
    }

    public function includes(Employee $employee, string $today): bool
    {
        return $this->employees($today)->whereKey($employee->id)->exists();
    }
}
