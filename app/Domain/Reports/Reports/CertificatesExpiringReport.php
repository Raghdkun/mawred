<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use App\Domain\Training\Models\Certificate;
use Carbon\CarbonImmutable;

/** Certificates that have expired or expire in the next N days, and whether a renewal is under way. */
final class CertificatesExpiringReport extends BaseReport
{
    public function key(): string
    {
        return 'training.certificates_expiring';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Training;
    }

    public function title(): array
    {
        return ['en' => 'Certificates expiring', 'ar' => 'الشهادات قريبة الانتهاء'];
    }

    public function description(): array
    {
        return ['en' => 'Expired certificates and those expiring soon, with renewals.', 'ar' => 'الشهادات المنتهية وتلك التي تنتهي قريبًا مع التجديدات.'];
    }

    public function permission(): string
    {
        return 'training.view';
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
            ReportColumn::text('certificate', 'Certificate', 'الشهادة'),
            ReportColumn::text('serial', 'Serial', 'الرقم التسلسلي'),
            new ReportColumn('expires_on', ['en' => 'Expires on', 'ar' => 'تاريخ الانتهاء'], ColumnType::Date),
            ReportColumn::number('days_left', 'Days left', 'الأيام المتبقية'),
            ReportColumn::text('renewal', 'Renewal', 'التجديد'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        $today = CarbonImmutable::parse($params->today());
        $ar = $locale === 'ar';
        $query = $this->placed($this->scoped(Certificate::query(), $user, SubjectBinding::employee('certificates.employee_id')), $params)
            ->join('employees', 'employees.id', '=', 'certificates.employee_id')
            ->whereNull('employees.termination_date')
            ->whereNull('certificates.revoked_at')
            ->whereNotNull('certificates.expires_on')
            ->where('certificates.expires_on', '<=', $params->until())
            ->oldest('certificates.expires_on')->orderBy('employees.employee_number')
            ->toBase()
            ->select([
                'employees.employee_number', 'employees.preferred_name', 'employees.name_en', 'employees.name_ar', 'employees.current_branch_id',
                'certificates.title', 'certificates.serial', 'certificates.expires_on', 'certificates.renewal_enrollment_id',
            ]);

        foreach ($query->cursor() as $row) {
            $expires = (string) self::text($row->expires_on);

            yield [
                'number' => (string) self::text($row->employee_number),
                'name' => Lookups::person(self::text($row->preferred_name), self::text($row->name_en), self::text($row->name_ar), $locale),
                'branch' => $names->branch(self::int($row->current_branch_id)),
                'certificate' => self::name($row->title, $locale),
                'serial' => (string) self::text($row->serial),
                'expires_on' => $expires,
                'days_left' => (int) $today->diffInDays(CarbonImmutable::parse($expires), false),
                'renewal' => $row->renewal_enrollment_id === null ? ($ar ? 'لا يوجد' : 'None') : ($ar ? 'أعيد التسجيل' : 'Re-enrolled'),
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $expired = count(array_filter($rows, fn (array $r): bool => $r['days_left'] < 0));
        $renewing = count(array_filter($rows, fn (array $r): bool => $r['renewal'] === ($ar ? 'أعيد التسجيل' : 'Re-enrolled')));

        return new ReportSummary([
            ['label' => $ar ? 'منتهية' : 'Expired', 'value' => $expired, 'format' => 'number'],
            ['label' => $ar ? 'تنتهي قريبًا' : 'Expiring soon', 'value' => count($rows) - $expired, 'format' => 'number'],
            ['label' => $ar ? 'قيد التجديد' : 'Renewing', 'value' => $renewing, 'format' => 'number'],
        ]);
    }
}
