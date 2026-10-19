<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Leave\Support\LeaveBalances;
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

/**
 * Leave balances for a year, one row per person and leave type, from `LeaveBalances` (ledger,
 * pending holds and projected accrual), never summed here.
 */
final class LeaveBalancesReport extends BaseReport
{
    public function __construct(ScopeResolver $resolver, private readonly LeaveBalances $balances)
    {
        parent::__construct($resolver);
    }

    public function key(): string
    {
        return 'leave.balances';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Leave;
    }

    public function title(): array
    {
        return ['en' => 'Leave balances', 'ar' => 'أرصدة الإجازات'];
    }

    public function description(): array
    {
        return ['en' => 'Entitlement, taken, held and available leave for each person.', 'ar' => 'الاستحقاق والمأخوذ والمحجوز والمتاح لكل موظف.'];
    }

    public function permission(): string
    {
        return 'leave.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('year', FilterType::Year), ...self::placementFilters()];
    }

    public function columns(): array
    {
        $decimal = fn (string $key, string $en, string $ar): ReportColumn => ReportColumn::number($key, $en, $ar, ColumnType::Decimal);

        return [
            ReportColumn::text('number', 'Employee no.', 'الرقم الوظيفي'),
            ReportColumn::text('name', 'Name', 'الاسم'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::text('type', 'Leave type', 'نوع الإجازة'),
            $decimal('entitlement', 'Entitlement', 'الاستحقاق'),
            $decimal('carried_over', 'Carried over', 'المرحّل'),
            $decimal('taken', 'Taken', 'المأخوذ'),
            $decimal('reserved', 'Pending', 'قيد الاعتماد'),
            $decimal('available', 'Available', 'المتاح'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        $year = $params->year();
        $query = $this->placed($this->scoped(Employee::query(), $user, SubjectBinding::employee('employees.id')), $params)
            ->where('employees.hire_date', '<=', "{$year}-12-31")
            ->where(fn (Builder $q) => $q->whereNull('employees.termination_date')->orWhere('employees.termination_date', '>=', "{$year}-01-01"))
            ->orderBy('employees.employee_number');

        foreach ($query->lazyById(200, 'employees.id', 'id') as $employee) {
            $base = [
                'number' => $employee->employee_number,
                'name' => $employee->displayName($locale),
                'branch' => $names->branch($employee->current_branch_id),
            ];

            foreach ($this->balances->all($employee, $year) as $balance) {
                yield [
                    ...$base,
                    'type' => $balance->name,
                    'entitlement' => round($balance->entitlement, 2),
                    'carried_over' => round($balance->carriedOver, 2),
                    'taken' => round($balance->taken, 2),
                    'reserved' => round($balance->reserved, 2),
                    'available' => round($balance->available, 2),
                ];
            }
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $byType = [];

        foreach ($rows as $row) {
            $type = (string) $row['type'];
            $byType[$type] ??= ['type' => $type, 'taken' => 0.0, 'available' => 0.0];
            $byType[$type]['taken'] = round($byType[$type]['taken'] + (float) $row['taken'], 2);
            $byType[$type]['available'] = round($byType[$type]['available'] + (float) $row['available'], 2);
        }

        return new ReportSummary(
            [
                ['label' => $locale === 'ar' ? 'الموظفون' : 'People', 'value' => count(array_unique(array_column($rows, 'number'))), 'format' => 'number'],
                ['label' => $locale === 'ar' ? 'المأخوذ' : 'Taken', 'value' => round(array_sum(array_column($rows, 'taken')), 2), 'format' => 'decimal'],
            ],
            $byType === [] ? null : ['type' => 'stacked', 'x' => 'type', 'series' => [
                ['key' => 'taken', 'label' => $locale === 'ar' ? 'المأخوذ' : 'Taken'],
                ['key' => 'available', 'label' => $locale === 'ar' ? 'المتاح' : 'Available'],
            ], 'data' => array_values($byType)],
        );
    }
}
