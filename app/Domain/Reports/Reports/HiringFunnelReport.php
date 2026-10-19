<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
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

/**
 * The hiring funnel per requisition for applications received in a period: how far each one got
 * (its current stage, or the stage where it was rejected).
 */
final class HiringFunnelReport extends BaseReport
{
    /** How far an application got: its stage kind in pipeline order (a hire counts as the end). */
    private const string RANK = "(case when job_applications.status = 'hired' then 4 else (case pipeline_stages.kind when 'screening' then 1 when 'interview' then 2 when 'offer' then 3 when 'hired' then 4 else 0 end) end)";

    public function __construct(ScopeResolver $resolver, private readonly HiringScope $hiring)
    {
        parent::__construct($resolver);
    }

    public function key(): string
    {
        return 'hiring.funnel';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Hiring;
    }

    public function title(): array
    {
        return ['en' => 'Hiring funnel', 'ar' => 'مسار التوظيف'];
    }

    public function description(): array
    {
        return ['en' => 'How far applications got, per requisition.', 'ar' => 'إلى أين وصلت الطلبات في كل طلب توظيف.'];
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
        return [
            ReportColumn::text('requisition', 'Requisition', 'طلب التوظيف'),
            ReportColumn::text('branch', 'Branch', 'الفرع'),
            ReportColumn::number('applied', 'Applied', 'تقدّموا'),
            ReportColumn::number('screening', 'Screened', 'تمت تصفيتهم'),
            ReportColumn::number('interview', 'Interviewed', 'أجريت مقابلتهم'),
            ReportColumn::number('offer', 'Offered', 'قُدّم لهم عرض'),
            ReportColumn::number('hired', 'Hired', 'تم توظيفهم'),
            ReportColumn::number('conversion', 'Applied to hired', 'نسبة التوظيف', ColumnType::Percent),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $names = new Lookups($locale);
        [$start, $end] = Instants::between($params->from(), $params->to());
        $query = $this->placed($this->resolver->apply(JobApplication::query(), $user, $this->permission(), $this->hiring->applications()), $params, 'job_requisitions.branch_id', 'job_requisitions.department_id')
            ->join('job_requisitions', 'job_requisitions.id', '=', 'job_applications.job_requisition_id')
            ->leftJoin('pipeline_stages', 'pipeline_stages.id', '=', 'job_applications.pipeline_stage_id')
            ->where('job_applications.applied_at', '>=', $start)
            ->where('job_applications.applied_at', '<', $end)
            ->toBase()
            ->select(['job_requisitions.id', 'job_requisitions.number', 'job_requisitions.title', 'job_requisitions.branch_id'])
            ->selectRaw('count(*) as applied')
            ->selectRaw('count(*) filter (where '.self::RANK.' >= 1) as screening')
            ->selectRaw('count(*) filter (where '.self::RANK.' >= 2) as interview')
            ->selectRaw('count(*) filter (where '.self::RANK.' >= 3) as offer')
            ->selectRaw('count(*) filter (where '.self::RANK.' >= 4) as hired')
            ->groupBy('job_requisitions.id', 'job_requisitions.number', 'job_requisitions.title', 'job_requisitions.branch_id')
            ->orderBy('job_requisitions.number');

        foreach ($query->cursor() as $row) {
            $applied = self::int($row->applied) ?? 0;
            $hired = self::int($row->hired) ?? 0;

            yield [
                'requisition' => self::text($row->number).' · '.self::name($row->title, $locale),
                'branch' => $names->branch(self::int($row->branch_id)),
                'applied' => $applied,
                'screening' => self::int($row->screening) ?? 0,
                'interview' => self::int($row->interview) ?? 0,
                'offer' => self::int($row->offer) ?? 0,
                'hired' => $hired,
                'conversion' => self::percent($hired, $applied),
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $sum = fn (string $key): int => (int) array_sum(array_column($rows, $key));

        return new ReportSummary(
            [
                ['label' => $ar ? 'الطلبات' : 'Applications', 'value' => $sum('applied'), 'format' => 'number'],
                ['label' => $ar ? 'تم توظيفهم' : 'Hired', 'value' => $sum('hired'), 'format' => 'number'],
                ['label' => $ar ? 'نسبة التوظيف' : 'Applied to hired', 'value' => self::percent($sum('hired'), $sum('applied')), 'format' => 'percent'],
            ],
            $rows === [] ? null : ['type' => 'bar', 'x' => 'stage', 'series' => [['key' => 'count', 'label' => $ar ? 'المتقدمون' : 'Applicants']], 'data' => [
                ['stage' => $ar ? 'تقدّموا' : 'Applied', 'count' => $sum('applied')],
                ['stage' => $ar ? 'التصفية' : 'Screened', 'count' => $sum('screening')],
                ['stage' => $ar ? 'المقابلة' : 'Interviewed', 'count' => $sum('interview')],
                ['stage' => $ar ? 'العرض' : 'Offered', 'count' => $sum('offer')],
                ['stage' => $ar ? 'التوظيف' : 'Hired', 'count' => $sum('hired')],
            ]],
        );
    }
}
