<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use Carbon\CarbonImmutable;

/** Employee documents that have expired or expire in the next N days (D112). */
final class DocumentsExpiringReport extends BaseReport
{
    public function key(): string
    {
        return 'people.documents_expiring';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::People;
    }

    public function title(): array
    {
        return ['en' => 'Documents expiring', 'ar' => 'المستندات قريبة الانتهاء'];
    }

    public function description(): array
    {
        return ['en' => 'Expired documents and those expiring soon.', 'ar' => 'المستندات المنتهية وتلك التي تنتهي قريبًا.'];
    }

    public function permission(): string
    {
        return 'people.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('days', FilterType::Days, default: 60), ...self::placementFilters()];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('number', 'Employee no.', 'الرقم الوظيفي'),
            ReportColumn::text('name', 'Name', 'الاسم'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::text('document', 'Document', 'المستند'),
            new ReportColumn('expires_on', ['en' => 'Expires on', 'ar' => 'تاريخ الانتهاء'], ColumnType::Date),
            ReportColumn::number('days_left', 'Days left', 'الأيام المتبقية'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        $today = CarbonImmutable::parse($params->today());
        $query = $this->placed($this->scoped(EmployeeDocument::query(), $user, SubjectBinding::employee('employee_documents.employee_id')), $params)
            ->join('employees', 'employees.id', '=', 'employee_documents.employee_id')
            ->join('document_types', 'document_types.id', '=', 'employee_documents.document_type_id')
            ->whereNull('employees.deleted_at')
            ->whereNull('employees.termination_date')
            ->whereNotNull('employee_documents.expires_on')
            ->where('employee_documents.expires_on', '<=', $params->until())
            ->oldest('employee_documents.expires_on')
            ->orderBy('employees.employee_number')
            ->toBase()
            ->select([
                'employees.employee_number', 'employees.preferred_name', 'employees.name_en', 'employees.name_ar', 'employees.current_branch_id',
                'document_types.name as type_name', 'employee_documents.expires_on',
            ]);

        foreach ($query->cursor() as $row) {
            $expires = (string) self::text($row->expires_on);

            yield [
                'number' => (string) self::text($row->employee_number),
                'name' => Lookups::person(self::text($row->preferred_name), self::text($row->name_en), self::text($row->name_ar), $locale),
                'branch' => $names->branch(self::int($row->current_branch_id)),
                'document' => self::name($row->type_name, $locale),
                'expires_on' => $expires,
                'days_left' => (int) $today->diffInDays(CarbonImmutable::parse($expires), false),
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $expired = count(array_filter($rows, fn (array $r): bool => $r['days_left'] < 0));
        $byType = [];

        foreach ($rows as $row) {
            $byType[(string) $row['document']] = ($byType[(string) $row['document']] ?? 0) + 1;
        }

        return new ReportSummary(
            [
                ['label' => $locale === 'ar' ? 'منتهية' : 'Expired', 'value' => $expired, 'format' => 'number'],
                ['label' => $locale === 'ar' ? 'تنتهي قريبًا' : 'Expiring soon', 'value' => count($rows) - $expired, 'format' => 'number'],
            ],
            $byType === [] ? null : ['type' => 'bar', 'x' => 'document', 'series' => [['key' => 'count', 'label' => $locale === 'ar' ? 'المستندات' : 'Documents']],
                'data' => array_map(fn (string $type, int $n): array => ['document' => $type, 'count' => $n], array_keys($byType), array_values($byType))],
        );
    }
}
