<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\HiringScope;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Job applications with their requisition, stage and outcome (hiring.view, scoped by the
 * requisitions the viewer can see). No candidate contact details.
 *
 * @extends BaseDataset<JobApplication>
 */
final class ApplicationsDataset extends BaseDataset
{
    public function __construct(ScopeResolver $resolver, private readonly HiringScope $hiring)
    {
        parent::__construct($resolver);
    }

    public function key(): string
    {
        return 'applications';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Hiring;
    }

    public function title(): array
    {
        return ['en' => 'Job applications', 'ar' => 'طلبات التوظيف'];
    }

    public function description(): array
    {
        return ['en' => 'Applications per requisition, with their stage, source and outcome.', 'ar' => 'الطلبات لكل وظيفة مع المرحلة والمصدر والنتيجة.'];
    }

    public function permission(): string
    {
        return 'hiring.view';
    }

    public function dateField(): string
    {
        return 'applied_on';
    }

    public function branchColumn(): string
    {
        return 'job_requisitions.branch_id';
    }

    public function departmentColumn(): string
    {
        return 'job_requisitions.department_id';
    }

    public function fields(): array
    {
        return [
            DatasetField::text('requisition', 'Requisition', 'طلب التوظيف', fn (string $locale): string => "job_requisitions.number || ' · ' || ".self::translated('job_requisitions.title')($locale)),
            DatasetField::text('branch', 'Branch', 'الفرع', self::translated('rb.name')),
            DatasetField::text('department', 'Department', 'القسم', self::translated('rd.name')),
            DatasetField::text('stage', 'Stage', 'المرحلة', self::translated('rps.name')),
            DatasetField::choice('status', 'Status', 'الحالة', 'job_applications.status', self::options([
                'active' => ['In progress', 'قيد المتابعة'], 'rejected' => ['Rejected', 'مرفوض'], 'withdrawn' => ['Withdrawn', 'منسحب'], 'hired' => ['Hired', 'تم توظيفه'],
            ])),
            DatasetField::choice('source', 'Source', 'المصدر', 'job_applications.source', self::options([
                'careers' => ['Careers site', 'صفحة الوظائف'], 'cognito' => ['Cognito form', 'نموذج Cognito'], 'manual' => ['Added by hand', 'أضيف يدويًا'],
                'referral' => ['Referral', 'ترشيح'], 'talent_pool' => ['Talent pool', 'قاعدة المواهب'],
            ])),
            DatasetField::date('applied_on', 'Applied on', 'تاريخ التقديم', self::day('job_applications.applied_at')),
            DatasetField::text('month', 'Month', 'الشهر', "to_char(job_applications.applied_at, 'YYYY-MM')"),
            DatasetField::number('days_in_stage', 'Days in stage', 'أيام في المرحلة', 'floor(extract(epoch from (now() - job_applications.stage_entered_at)) / 86400)', ColumnType::Number),
            DatasetField::number('score', 'Score', 'التقييم', 'job_applications.score', ColumnType::Decimal),
        ];
    }

    public function query(User $user): Builder
    {
        return $this->resolver->apply($this->base(), $user, $this->permission(), $this->hiring->applications());
    }

    protected function base(): Builder
    {
        return JobApplication::query()
            ->join('job_requisitions', 'job_requisitions.id', '=', 'job_applications.job_requisition_id')
            ->leftJoin('pipeline_stages as rps', 'rps.id', '=', 'job_applications.pipeline_stage_id')
            ->leftJoin('branches as rb', 'rb.id', '=', 'job_requisitions.branch_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'job_requisitions.department_id');
    }

    protected function subject(): SubjectBinding
    {
        return $this->hiring->applications();
    }
}
