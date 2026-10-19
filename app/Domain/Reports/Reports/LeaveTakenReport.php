<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;

/** Approved leave that overlaps a period, one row per request, optionally for one leave type. */
final class LeaveTakenReport extends BaseReport
{
    public function key(): string
    {
        return 'leave.taken';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Leave;
    }

    public function title(): array
    {
        return ['en' => 'Leave taken', 'ar' => 'الإجازات المأخوذة'];
    }

    public function description(): array
    {
        return ['en' => 'Approved leave in a period, by type.', 'ar' => 'الإجازات المعتمدة خلال فترة حسب النوع.'];
    }

    public function permission(): string
    {
        return 'leave.view';
    }

    public function filters(): array
    {
        $types = array_values(LeaveType::query()->orderBy('sort_order')->orderBy('id')->get(['ulid', 'name'])
            ->map(fn (LeaveType $type): array => ['value' => $type->ulid, 'label' => ['en' => self::name($type->getTranslations('name'), 'en'), 'ar' => self::name($type->getTranslations('name'), 'ar')]])
            ->all());

        return [
            new ReportFilter('period', FilterType::Period),
            new ReportFilter('leave_type', FilterType::Choice, label: ['en' => 'Leave type', 'ar' => 'نوع الإجازة'], options: $types),
            ...self::placementFilters(),
        ];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('number', 'Employee no.', 'الرقم الوظيفي'),
            ReportColumn::text('name', 'Name', 'الاسم'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::text('type', 'Leave type', 'نوع الإجازة'),
            new ReportColumn('from', ['en' => 'From', 'ar' => 'من'], ColumnType::Date),
            new ReportColumn('to', ['en' => 'To', 'ar' => 'إلى'], ColumnType::Date),
            ReportColumn::number('amount', 'Amount', 'المدة', ColumnType::Decimal),
            ReportColumn::text('unit', 'Unit', 'الوحدة'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        $type = $params->string('leave_type');
        $query = $this->placed($this->scoped(LeaveRequest::query(), $user, SubjectBinding::employee('leave_requests.employee_id')), $params)
            ->join('employees', 'employees.id', '=', 'leave_requests.employee_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', LeaveRequestStatus::Approved->value)
            ->where('leave_requests.starts_on', '<=', $params->to())
            ->where('leave_requests.ends_on', '>=', $params->from())
            ->when($type, fn ($q, string $ulid) => $q->where('leave_types.ulid', $ulid))
            ->oldest('leave_requests.starts_on')->orderBy('employees.employee_number')
            ->toBase()
            ->select([
                'employees.employee_number', 'employees.preferred_name', 'employees.name_en', 'employees.name_ar', 'employees.current_branch_id',
                'leave_types.name as type_name', 'leave_types.unit', 'leave_requests.starts_on', 'leave_requests.ends_on', 'leave_requests.amount',
            ]);
        $hours = ['en' => 'Hours', 'ar' => 'ساعات'];
        $days = ['en' => 'Days', 'ar' => 'أيام'];

        foreach ($query->cursor() as $row) {
            yield [
                'number' => (string) self::text($row->employee_number),
                'name' => Lookups::person(self::text($row->preferred_name), self::text($row->name_en), self::text($row->name_ar), $locale),
                'branch' => $names->branch(self::int($row->current_branch_id)),
                'type' => self::name($row->type_name, $locale),
                'from' => (string) self::text($row->starts_on),
                'to' => (string) self::text($row->ends_on),
                'amount' => round(self::float($row->amount), 2),
                'unit' => ($row->unit === 'hours' ? $hours : $days)[$locale === 'ar' ? 'ar' : 'en'],
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $byType = [];

        foreach ($rows as $row) {
            $byType[(string) $row['type']] = round(($byType[(string) $row['type']] ?? 0) + (float) $row['amount'], 2);
        }

        return new ReportSummary(
            [
                ['label' => $locale === 'ar' ? 'الطلبات' : 'Requests', 'value' => count($rows), 'format' => 'number'],
                ['label' => $locale === 'ar' ? 'الموظفون' : 'People', 'value' => count(array_unique(array_column($rows, 'number'))), 'format' => 'number'],
            ],
            $byType === [] ? null : ['type' => 'bar', 'x' => 'type', 'series' => [['key' => 'amount', 'label' => $locale === 'ar' ? 'المدة' : 'Amount']],
                'data' => array_map(fn (string $type, float $amount): array => ['type' => $type, 'amount' => $amount], array_keys($byType), array_values($byType))],
        );
    }
}
