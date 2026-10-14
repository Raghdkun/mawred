<?php

declare(strict_types=1);

namespace App\Domain\People\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Support\Search\SearchText;
use Illuminate\Database\Eloquent\Builder;

/**
 * People directory (screen 7), people pickers and exports. Visibility follows the viewer's
 * `people.view` scope. Indexes: employees (status, name_en), (current_branch_id, status),
 * (current_department_id, status), (status, name_ar_sort), current_manager_id, and the search_text
 * trigram index.
 *
 * @extends ScopedQuery<Employee>
 */
final class EmployeeDirectoryQuery extends ScopedQuery
{
    public const array SORTS = ['name', 'number', 'hire_date'];

    private ?string $search = null;

    /** @var list<int> */
    private array $branchIds = [];

    private ?Department $department = null;

    /** @var list<int> */
    private array $positionIds = [];

    /** @var list<int> */
    private array $employmentTypeIds = [];

    private ?int $managerId = null;

    /** @var list<EmployeeStatus> */
    private array $statuses = [EmployeeStatus::Pending, EmployeeStatus::Active, EmployeeStatus::Suspended];

    private string $sort = 'name';

    private bool $descending = false;

    private string $nameLocale = 'en';

    public function search(?string $term): self
    {
        $this->search = $term !== null && trim($term) !== '' ? $term : null;

        return $this;
    }

    /**
     * @param  list<int>  $branchIds
     */
    public function inBranches(array $branchIds): self
    {
        $this->branchIds = $branchIds;

        return $this;
    }

    /** Includes the department's whole subtree. */
    public function inDepartment(?Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    /**
     * @param  list<int>  $positionIds
     */
    public function withPositions(array $positionIds): self
    {
        $this->positionIds = $positionIds;

        return $this;
    }

    /**
     * @param  list<int>  $employmentTypeIds
     */
    public function withEmploymentTypes(array $employmentTypeIds): self
    {
        $this->employmentTypeIds = $employmentTypeIds;

        return $this;
    }

    public function reportingTo(?int $managerId): self
    {
        $this->managerId = $managerId;

        return $this;
    }

    /**
     * @param  list<EmployeeStatus>  $statuses  empty = every status
     */
    public function withStatuses(array $statuses): self
    {
        $this->statuses = $statuses;

        return $this;
    }

    public function sortBy(string $sort, bool $descending = false): self
    {
        $this->sort = in_array($sort, self::SORTS, true) ? $sort : 'name';
        $this->descending = $descending;

        return $this;
    }

    /** Sort names in this language (Arabic falls back to the English name). */
    public function namesIn(string $locale): self
    {
        $this->nameLocale = $locale;

        return $this;
    }

    protected function permission(): string
    {
        return 'people.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('employees.id');
    }

    protected function baseQuery(): Builder
    {
        $direction = $this->descending ? 'desc' : 'asc';
        $column = match ($this->sort) {
            'number' => 'employees.employee_number',
            'hire_date' => 'employees.hire_date',
            default => $this->nameLocale === 'ar' ? 'employees.name_ar_sort' : 'employees.name_en',
        };

        return Employee::query()
            ->select('employees.*')
            ->with([
                'currentBranch:id,ulid,code,name',
                'currentDepartment:id,ulid,code,name',
                'currentPosition:id,ulid,code,title',
                'currentManager:id,ulid,name_en,name_ar,preferred_name',
            ])
            ->when($this->search, fn (Builder $query, string $term) => $query->where('employees.search_text', 'like', SearchText::likePattern($term)))
            ->when($this->branchIds !== [], fn (Builder $query) => $query->whereIn('employees.current_branch_id', $this->branchIds))
            ->when($this->department, fn (Builder $query, Department $department) => $query->whereIn(
                'employees.current_department_id',
                Department::query()->select('id')->whereRaw('path <@ ?::ltree', [$department->path]),
            ))
            ->when($this->positionIds !== [], fn (Builder $query) => $query->whereIn('employees.current_position_id', $this->positionIds))
            ->when($this->employmentTypeIds !== [], fn (Builder $query) => $query->whereIn('employees.current_employment_type_id', $this->employmentTypeIds))
            ->when($this->managerId, fn (Builder $query, int $managerId) => $query->where('employees.current_manager_id', $managerId))
            ->when($this->statuses !== [], fn (Builder $query) => $query->whereIn('employees.status', array_map(fn (EmployeeStatus $s): string => $s->value, $this->statuses)))
            ->orderBy($column, $direction)
            ->orderBy('employees.id', $direction);
    }
}
