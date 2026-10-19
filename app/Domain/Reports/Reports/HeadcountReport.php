<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use Illuminate\Contracts\Database\Query\Builder;

/** People employed on a day, by branch and department (current placement). */
final class HeadcountReport extends BaseReport
{
    public function key(): string
    {
        return 'people.headcount';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::People;
    }

    public function title(): array
    {
        return ['en' => 'Headcount', 'ar' => 'عدد الموظفين'];
    }

    public function description(): array
    {
        return ['en' => 'People employed on a day, by branch and department.', 'ar' => 'الموظفون في يوم محدد حسب الفرع والقسم.'];
    }

    public function permission(): string
    {
        return 'people.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('as_of', FilterType::AsOf), ...self::placementFilters()];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::text('department', 'Department', 'القسم'),
            ReportColumn::number('headcount', 'Headcount', 'العدد'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $day = $params->asOf();
        $names = new Lookups($locale);
        $query = $this->placed($this->scoped(Employee::query(), $user, SubjectBinding::employee('employees.id')), $params)
            ->where('employees.hire_date', '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull('employees.termination_date')->orWhere('employees.termination_date', '>=', $day))
            ->toBase()
            ->selectRaw('employees.current_branch_id as branch_id, employees.current_department_id as department_id, count(*) as n')
            ->groupBy('employees.current_branch_id', 'employees.current_department_id')
            ->orderBy('employees.current_branch_id')->orderBy('employees.current_department_id');

        foreach ($query->cursor() as $row) {
            yield [
                'branch' => $names->branch(self::int($row->branch_id ?? null)),
                'department' => $names->department(self::int($row->department_id ?? null)),
                'headcount' => self::int($row->n ?? null) ?? 0,
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $byBranch = [];
        $byDepartment = [];

        foreach ($rows as $row) {
            $branch = (string) $row['branch'];
            $byBranch[$branch] = ($byBranch[$branch] ?? 0) + (int) $row['headcount'];
            $byDepartment[(string) $row['department']] = ($byDepartment[(string) $row['department']] ?? 0) + (int) $row['headcount'];
        }

        // One branch (a branch manager, or a branch filter): the departments say more than one bar.
        [$x, $groups] = count($byBranch) === 1 ? ['department', $byDepartment] : ['branch', $byBranch];

        return new ReportSummary(
            [
                ['label' => $locale === 'ar' ? 'إجمالي الموظفين' : 'People', 'value' => array_sum($byBranch), 'format' => 'number'],
                ['label' => $locale === 'ar' ? 'الفروع' : 'Branches', 'value' => count($byBranch), 'format' => 'number'],
            ],
            $groups === [] ? null : ['type' => 'bar', 'x' => $x, 'series' => [['key' => 'headcount', 'label' => $locale === 'ar' ? 'العدد' : 'Headcount']],
                'data' => array_map(fn (string $name, int $n): array => [$x => $name, 'headcount' => $n], array_keys($groups), array_values($groups))],
        );
    }
}
