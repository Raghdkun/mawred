<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\HiringScope;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Instants;
use App\Domain\Reports\Support\Lookups;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use Illuminate\Support\Facades\DB;

/**
 * People hired in a period, per requisition: days from application to hire, and from the
 * requisition opening to the hire.
 */
final class TimeToHireReport extends BaseReport
{
    public function __construct(ScopeResolver $resolver, private readonly HiringScope $hiring)
    {
        parent::__construct($resolver);
    }

    public function key(): string
    {
        return 'hiring.time_to_hire';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Hiring;
    }

    public function title(): array
    {
        return ['en' => 'Time to hire', 'ar' => 'مدة التوظيف'];
    }

    public function description(): array
    {
        return ['en' => 'Days from application, and from opening the requisition, to the hire.', 'ar' => 'الأيام من التقديم ومن فتح طلب التوظيف حتى التعيين.'];
    }

    public function permission(): string
    {
        return 'hiring.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('period', FilterType::Period), ...self::placementFilters()];
    }

    public function columns(): array
    {
        $decimal = fn (string $key, string $en, string $ar): ReportColumn => ReportColumn::number($key, $en, $ar, ColumnType::Decimal);

        return [
            ReportColumn::text('requisition', 'Requisition', 'طلب التوظيف'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::number('hires', 'Hires', 'التعيينات'),
            $decimal('average_days', 'Average days to hire', 'متوسط أيام التوظيف'),
            ReportColumn::number('fastest', 'Fastest (days)', 'الأسرع (أيام)'),
            ReportColumn::number('slowest', 'Slowest (days)', 'الأبطأ (أيام)'),
            $decimal('days_to_fill', 'Average days to fill', 'متوسط أيام شغل الوظيفة'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        [$start, $end] = Instants::between($params->from(), $params->to());
        $hiredEvents = DB::table('application_events')
            ->select('job_application_id')->selectRaw('max(created_at) as hired_at')
            ->where('kind', ApplicationEventKind::Hired->value)
            ->groupBy('job_application_id');
        $hired = 'coalesce(hired_events.hired_at, job_applications.stage_entered_at)';
        $query = $this->placed($this->resolver->apply(JobApplication::query(), $user, $this->permission(), $this->hiring->applications()), $params, 'job_requisitions.branch_id', 'job_requisitions.department_id')
            ->join('job_requisitions', 'job_requisitions.id', '=', 'job_applications.job_requisition_id')
            ->leftJoinSub($hiredEvents, 'hired_events', 'hired_events.job_application_id', '=', 'job_applications.id')
            ->where('job_applications.status', ApplicationStatus::Hired->value)
            ->whereRaw("{$hired} >= ?", [$start])
            ->whereRaw("{$hired} < ?", [$end])
            ->toBase()
            ->select(['job_requisitions.id', 'job_requisitions.number', 'job_requisitions.title', 'job_requisitions.branch_id'])
            ->selectRaw('count(*) as hires')
            ->selectRaw("avg(extract(epoch from ({$hired} - job_applications.applied_at)) / 86400) as average_days")
            ->selectRaw("min(floor(extract(epoch from ({$hired} - job_applications.applied_at)) / 86400)) as fastest")
            ->selectRaw("max(floor(extract(epoch from ({$hired} - job_applications.applied_at)) / 86400)) as slowest")
            ->selectRaw("avg(extract(epoch from ({$hired} - job_requisitions.opened_at)) / 86400) as days_to_fill")
            ->groupBy('job_requisitions.id', 'job_requisitions.number', 'job_requisitions.title', 'job_requisitions.branch_id')
            ->orderBy('job_requisitions.number');

        foreach ($query->cursor() as $row) {
            yield [
                'requisition' => self::text($row->number).' · '.self::name($row->title, $locale),
                'branch' => $names->branch(self::int($row->branch_id)),
                'hires' => self::int($row->hires) ?? 0,
                'average_days' => round(self::float($row->average_days), 1),
                'fastest' => self::int($row->fastest) ?? 0,
                'slowest' => self::int($row->slowest) ?? 0,
                'days_to_fill' => $row->days_to_fill === null ? null : round(self::float($row->days_to_fill), 1),
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $hires = (int) array_sum(array_column($rows, 'hires'));
        $average = $hires > 0 ? round(array_sum(array_map(fn (array $r): float => (float) $r['average_days'] * (int) $r['hires'], $rows)) / $hires, 1) : 0.0;

        return new ReportSummary(
            [
                ['label' => $ar ? 'التعيينات' : 'Hires', 'value' => $hires, 'format' => 'number'],
                ['label' => $ar ? 'متوسط أيام التوظيف' : 'Average days to hire', 'value' => $average, 'format' => 'decimal'],
            ],
            $rows === [] ? null : ['type' => 'bar', 'x' => 'requisition', 'series' => [['key' => 'average_days', 'label' => $ar ? 'متوسط الأيام' : 'Average days']],
                'data' => array_map(fn (array $r): array => ['requisition' => $r['requisition'], 'average_days' => $r['average_days']], $rows)],
        );
    }
}
