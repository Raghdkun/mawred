<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use Illuminate\Contracts\Database\Query\Builder;

/** Who joined and who left in a period. */
final class MovementsReport extends BaseReport
{
    public function key(): string
    {
        return 'people.movements';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::People;
    }

    public function title(): array
    {
        return ['en' => 'Joiners and leavers', 'ar' => 'المنضمون والمغادرون'];
    }

    public function description(): array
    {
        return ['en' => 'Who joined and who left in a period.', 'ar' => 'من انضم ومن غادر خلال فترة.'];
    }

    public function permission(): string
    {
        return 'people.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('period', FilterType::Period), ...self::placementFilters()];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('number', 'Employee no.', 'الرقم الوظيفي'),
            ReportColumn::text('name', 'Name', 'الاسم'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::text('department', 'Department', 'القسم'),
            ReportColumn::text('movement', 'Movement', 'الحركة'),
            new ReportColumn('date', ['en' => 'Date', 'ar' => 'التاريخ'], ColumnType::Date),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        [$from, $to] = [$params->from(), $params->to()];
        $query = $this->placed($this->scoped(Employee::query(), $user, SubjectBinding::employee('employees.id')), $params)
            ->where(fn (Builder $q) => $q->whereBetween('employees.hire_date', [$from, $to])->orWhereBetween('employees.termination_date', [$from, $to]))
            ->orderBy('employees.employee_number');

        foreach ($query->cursor() as $employee) {
            $name = Lookups::person($employee->preferred_name, $employee->name_en, $employee->name_ar, $locale);
            $base = ['number' => $employee->employee_number, 'name' => $name, 'branch' => $names->branch($employee->current_branch_id), 'department' => $names->department($employee->current_department_id)];

            if ($employee->hire_date->toDateString() >= $from && $employee->hire_date->toDateString() <= $to) {
                yield [...$base, 'movement' => $locale === 'ar' ? 'انضم' : 'Joined', 'date' => $employee->hire_date->toDateString()];
            }

            $left = $employee->termination_date?->toDateString();

            if ($left !== null && $left >= $from && $left <= $to) {
                yield [...$base, 'movement' => $locale === 'ar' ? 'غادر' : 'Left', 'date' => $left];
            }
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $joined = $locale === 'ar' ? 'انضم' : 'Joined';
        $in = count(array_filter($rows, fn (array $r): bool => $r['movement'] === $joined));
        $out = count($rows) - $in;
        $months = [];

        foreach ($rows as $row) {
            $month = substr((string) $row['date'], 0, 7);
            $months[$month] ??= ['month' => $month, 'joined' => 0, 'left' => 0];
            $months[$month][$row['movement'] === $joined ? 'joined' : 'left']++;
        }

        ksort($months);

        return new ReportSummary(
            [
                ['label' => $locale === 'ar' ? 'المنضمون' : 'Joiners', 'value' => $in, 'format' => 'number'],
                ['label' => $locale === 'ar' ? 'المغادرون' : 'Leavers', 'value' => $out, 'format' => 'number'],
                ['label' => $locale === 'ar' ? 'الصافي' : 'Net change', 'value' => $in - $out, 'format' => 'number'],
            ],
            ['type' => 'bar', 'x' => 'month', 'series' => [
                ['key' => 'joined', 'label' => $locale === 'ar' ? 'المنضمون' : 'Joiners'],
                ['key' => 'left', 'label' => $locale === 'ar' ? 'المغادرون' : 'Leavers'],
            ], 'data' => array_values($months)],
        );
    }
}
