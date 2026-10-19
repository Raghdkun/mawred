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

/**
 * Turnover by branch: leavers in the period over the average of the headcount at its start and
 * its end.
 */
final class TurnoverReport extends BaseReport
{
    public function key(): string
    {
        return 'people.turnover';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::People;
    }

    public function title(): array
    {
        return ['en' => 'Turnover', 'ar' => 'معدل دوران الموظفين'];
    }

    public function description(): array
    {
        return ['en' => 'Leavers against the average headcount, by branch.', 'ar' => 'المغادرون مقابل متوسط عدد الموظفين حسب الفرع.'];
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
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::number('start', 'Headcount at start', 'العدد في البداية'),
            ReportColumn::number('end', 'Headcount at end', 'العدد في النهاية'),
            ReportColumn::number('leavers', 'Leavers', 'المغادرون'),
            ReportColumn::number('turnover', 'Turnover', 'معدل الدوران', ColumnType::Percent),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        [$from, $to] = [$params->from(), $params->to()];
        $employed = 'count(*) filter (where employees.hire_date <= ? and (employees.termination_date is null or employees.termination_date >= ?))';
        $query = $this->placed($this->scoped(Employee::query(), $user, SubjectBinding::employee('employees.id')), $params)
            ->toBase()
            ->selectRaw('employees.current_branch_id as branch_id')
            ->selectRaw($employed.' as start_count', [$from, $from])
            ->selectRaw($employed.' as end_count', [$to, $to])
            ->selectRaw('count(*) filter (where employees.termination_date between ? and ?) as leavers', [$from, $to])
            ->groupBy('employees.current_branch_id')
            ->orderBy('employees.current_branch_id');

        foreach ($query->cursor() as $row) {
            $start = self::int($row->start_count ?? null) ?? 0;
            $end = self::int($row->end_count ?? null) ?? 0;
            $leavers = self::int($row->leavers ?? null) ?? 0;

            yield [
                'branch' => $names->branch(self::int($row->branch_id ?? null)),
                'start' => $start,
                'end' => $end,
                'leavers' => $leavers,
                'turnover' => self::percent($leavers, ($start + $end) / 2),
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $start = array_sum(array_column($rows, 'start'));
        $end = array_sum(array_column($rows, 'end'));
        $leavers = array_sum(array_column($rows, 'leavers'));

        return new ReportSummary(
            [
                ['label' => $locale === 'ar' ? 'معدل الدوران' : 'Turnover', 'value' => self::percent($leavers, ($start + $end) / 2), 'format' => 'percent'],
                ['label' => $locale === 'ar' ? 'المغادرون' : 'Leavers', 'value' => $leavers, 'format' => 'number'],
            ],
            ['type' => 'bar', 'x' => 'branch', 'series' => [['key' => 'turnover', 'label' => $locale === 'ar' ? 'معدل الدوران (%)' : 'Turnover (%)']],
                'data' => array_map(fn (array $r): array => ['branch' => $r['branch'], 'turnover' => $r['turnover']], $rows)],
        );
    }
}
