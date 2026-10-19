<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\FilterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared plumbing for prebuilt reports: scoping a query through the ScopeResolver with the
 * report's permission (never hand-rolled), the usual branch and department narrowing, and labels.
 */
abstract class BaseReport implements Report
{
    public function __construct(protected readonly ScopeResolver $resolver) {}

    /**
     * Limits a query to what the user may see under the report's permission.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function scoped(Builder $query, User $user, SubjectBinding $subject): Builder
    {
        return $this->resolver->apply($query, $user, $this->permission(), $subject);
    }

    /**
     * Narrows to a branch and a department (with its sub-departments) by employee columns.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function placed(Builder $query, ReportParams $params, string $branchColumn = 'employees.current_branch_id', string $departmentColumn = 'employees.current_department_id'): Builder
    {
        $branch = $params->branch();
        $department = $params->department();

        if ($branch instanceof Branch) {
            $query->where($branchColumn, $branch->id);
        }

        if ($department instanceof Department) {
            $query->whereIn($departmentColumn, Department::query()->select('id')->whereRaw('path <@ ?::ltree', [$department->path]));
        }

        return $query;
    }

    /** @return list<ReportFilter> */
    protected static function placementFilters(): array
    {
        return [new ReportFilter('branch', FilterType::Branch), new ReportFilter('department', FilterType::Department)];
    }

    /**
     * @param  mixed  $translations  a translatable column's array, or its raw jsonb
     */
    protected static function name(mixed $translations, string $locale): string
    {
        if (is_string($translations) && str_starts_with($translations, '{')) {
            $translations = json_decode($translations, true);
        }

        if (! is_array($translations)) {
            return is_string($translations) ? $translations : '';
        }

        $value = $translations[$locale] ?? $translations['en'] ?? null;

        return is_string($value) ? $value : '';
    }

    /** A raw column as a string, or null. */
    protected static function text(mixed $value): ?string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }

    /** A raw column as an int, or null. */
    protected static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** A raw column as a float. */
    protected static function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    protected static function percent(int|float $part, int|float $whole): float
    {
        return $whole <= 0 ? 0.0 : round($part * 100 / $whole, 1);
    }
}
