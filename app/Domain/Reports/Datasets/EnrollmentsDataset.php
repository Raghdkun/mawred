<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Training\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;

/** Course enrolments with progress, due dates and completion (training.view). *
 * @extends BaseDataset<Enrollment>
 */
final class EnrollmentsDataset extends BaseDataset
{
    public function key(): string
    {
        return 'enrollments';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Training;
    }

    public function title(): array
    {
        return ['en' => 'Training enrolments', 'ar' => 'التسجيل في التدريب'];
    }

    public function description(): array
    {
        return ['en' => 'Who is on which course, how far along, and when it\'s due.', 'ar' => 'من يدرس أي دورة ومدى تقدمه وموعد الاستحقاق.'];
    }

    public function permission(): string
    {
        return 'training.view';
    }

    public function dateField(): string
    {
        return 'enrolled_on';
    }

    public function fields(): array
    {
        return [
            ...self::personFields(),
            DatasetField::text('course', 'Course', 'الدورة', fn (string $locale): string => "rc.code || ' · ' || ".self::translated('rc.title')($locale)),
            DatasetField::choice('status', 'Status', 'الحالة', 'enrollments.status', self::options([
                'requested' => ['Waiting for approval', 'بانتظار الموافقة'], 'enrolled' => ['In progress', 'قيد التعلّم'], 'completed' => ['Completed', 'مكتمل'],
                'rejected' => ['Not approved', 'غير معتمد'], 'cancelled' => ['Cancelled', 'ملغى'],
            ])),
            DatasetField::choice('source', 'How they joined', 'طريقة التسجيل', 'enrollments.source', self::options([
                'self' => ['Chose it', 'باختياره'], 'assigned' => ['Assigned', 'مُسند'], 'audience' => ['Required for their role', 'إلزامي لدوره'],
                'path' => ['Learning path', 'مسار تعليمي'], 'request' => ['Training request', 'طلب تدريب'], 'renewal' => ['Renewal', 'تجديد'],
            ])),
            DatasetField::date('enrolled_on', 'Enrolled on', 'تاريخ التسجيل', self::day('coalesce(enrollments.enrolled_at, enrollments.created_at)')),
            DatasetField::date('due_on', 'Due', 'موعد الاستحقاق', 'enrollments.due_on'),
            DatasetField::date('completed_on', 'Completed on', 'تاريخ الإكمال', self::day('enrollments.completed_at')),
            DatasetField::number('progress', 'Progress (%)', 'التقدم (%)', 'enrollments.progress'),
        ];
    }

    protected function base(): Builder
    {
        return Enrollment::query()
            ->join('employees', 'employees.id', '=', 'enrollments.employee_id')
            ->join('courses as rc', 'rc.id', '=', 'enrollments.course_id')
            ->leftJoin('branches as rb', 'rb.id', '=', 'employees.current_branch_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'employees.current_department_id');
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('enrollments.employee_id');
    }
}
