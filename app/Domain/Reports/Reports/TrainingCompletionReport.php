<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Instants;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;

/** Per course on a day: who is enrolled, who finished, who is still going and who is overdue. */
final class TrainingCompletionReport extends BaseReport
{
    public function key(): string
    {
        return 'training.completion';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Training;
    }

    public function title(): array
    {
        return ['en' => 'Training completion', 'ar' => 'إتمام التدريب'];
    }

    public function description(): array
    {
        return ['en' => 'Enrolled, completed and overdue learners per course.', 'ar' => 'المسجلون والمكملون والمتأخرون في كل دورة.'];
    }

    public function permission(): string
    {
        return 'training.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('as_of', FilterType::AsOf), ...self::placementFilters()];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('course', 'Course', 'الدورة'),
            ReportColumn::number('enrolled', 'Enrolled', 'المسجلون'),
            ReportColumn::number('completed', 'Completed', 'أكملوا'),
            ReportColumn::number('in_progress', 'In progress', 'قيد التعلّم'),
            ReportColumn::number('overdue', 'Overdue', 'متأخرون'),
            ReportColumn::number('completion', 'Completion', 'نسبة الإتمام', ColumnType::Percent),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        $day = $params->asOf();
        $completed = EnrollmentStatus::Completed->value;
        $query = $this->placed($this->scoped(Enrollment::query(), $user, SubjectBinding::employee('enrollments.employee_id')), $params)
            ->join('employees', 'employees.id', '=', 'enrollments.employee_id')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->whereIn('enrollments.status', [EnrollmentStatus::Enrolled->value, $completed])
            ->whereRaw('coalesce(enrollments.enrolled_at, enrollments.created_at) < ?', [Instants::between($day, $day)[1]])
            ->toBase()
            ->select(['courses.id', 'courses.code', 'courses.title'])
            ->selectRaw('count(*) as enrolled')
            ->selectRaw('count(*) filter (where enrollments.status = ?) as completed', [$completed])
            ->selectRaw('count(*) filter (where enrollments.status <> ? and enrollments.due_on < ?) as overdue', [$completed, $day])
            ->groupBy('courses.id', 'courses.code', 'courses.title')
            ->orderBy('courses.code');

        foreach ($query->cursor() as $row) {
            $enrolled = self::int($row->enrolled) ?? 0;
            $done = self::int($row->completed) ?? 0;

            yield [
                'course' => self::text($row->code).' · '.self::name($row->title, $locale),
                'enrolled' => $enrolled,
                'completed' => $done,
                'in_progress' => $enrolled - $done,
                'overdue' => self::int($row->overdue) ?? 0,
                'completion' => self::percent($done, $enrolled),
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $sum = fn (string $key): int => (int) array_sum(array_column($rows, $key));

        return new ReportSummary(
            [
                ['label' => $ar ? 'نسبة الإتمام' : 'Completion', 'value' => self::percent($sum('completed'), $sum('enrolled')), 'format' => 'percent'],
                ['label' => $ar ? 'المسجلون' : 'Enrolled', 'value' => $sum('enrolled'), 'format' => 'number'],
                ['label' => $ar ? 'متأخرون' : 'Overdue', 'value' => $sum('overdue'), 'format' => 'number'],
            ],
            $rows === [] ? null : ['type' => 'stacked', 'x' => 'course', 'series' => [
                ['key' => 'completed', 'label' => $ar ? 'أكملوا' : 'Completed'],
                ['key' => 'in_progress', 'label' => $ar ? 'قيد التعلّم' : 'In progress'],
            ], 'data' => array_map(fn (array $r): array => ['course' => $r['course'], 'completed' => $r['completed'], 'in_progress' => $r['in_progress']], $rows)],
        );
    }
}
