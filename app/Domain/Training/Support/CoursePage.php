<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Data\CourseCardData;
use App\Domain\Training\Data\EnrollmentData;
use App\Domain\Training\Data\ModuleSummaryData;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\LearningPath;
use App\Domain\Training\Models\ModuleProgress;
use App\Domain\Training\Models\QuizAttempt;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Builds what a course page shows a viewer (handoff 33): the course, its outline with their
 * progress, their latest enrolment, the paths it belongs to and what they may do.
 */
final readonly class CoursePage
{
    public function __construct(private LearnerClock $clock) {}

    public static function employeeOf(User $user): ?Employee
    {
        return Employee::query()->where('user_id', $user->id)->first();
    }

    /** The viewer's live enrolment, else their latest one. */
    public static function latest(?Employee $employee, Course $course): ?Enrollment
    {
        if (! $employee instanceof Employee) {
            return null;
        }

        return Enrollment::query()->where('employee_id', $employee->id)->where('course_id', $course->id)
            ->orderByRaw("case when status in ('requested', 'enrolled') then 0 else 1 end")
            ->latest('created_at')->orderByDesc('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function for(Course $course, User $user, string $locale): array
    {
        $employee = self::employeeOf($user);
        $today = $this->clock->today($employee);
        $enrollment = self::latest($employee, $course);
        $course->loadCount('modules');

        $done = $enrollment instanceof Enrollment ? ModuleProgress::query()->where('enrollment_id', $enrollment->id)->pluck('course_module_id')->all() : [];
        $attempts = [];

        if ($enrollment instanceof Enrollment) {
            foreach (QuizAttempt::query()->where('enrollment_id', $enrollment->id)->oldest('submitted_at')->get() as $attempt) {
                $attempts[$attempt->course_module_id][] = ['score' => $attempt->score, 'passed' => $attempt->passed];
            }
        }

        $paths = LearningPath::query()->where('status', CourseStatus::Published->value)
            ->whereHas('courses', fn (Builder $q) => $q->whereKey($course->id))->get();
        $description = FieldSpec::text($course->getTranslations('description'), $locale);

        return [
            'course' => CourseCardData::fromCourse($course, $enrollment, $locale, $today),
            'description' => $description === '' ? null : $description,
            'validityMonths' => $course->issues_certificate ? $course->validity_months : null,
            'status' => $course->status->value,
            'modules' => array_values($course->modules()->get()->map(fn (CourseModule $module): ModuleSummaryData => ModuleSummaryData::fromModule(
                $module, $locale, in_array($module->id, $done, true), $attempts[$module->id] ?? [],
            ))->all()),
            'enrollment' => $enrollment instanceof Enrollment ? EnrollmentData::fromEnrollment($enrollment, $locale, $today) : null,
            'paths' => array_values($paths->map(fn (LearningPath $path): array => ['id' => $path->ulid, 'title' => FieldSpec::text($path->getTranslations('title'), $locale)])->all()),
            'can' => [
                'enrol' => $course->status === CourseStatus::Published && Gate::allows('training.enrol')
                    && (! $enrollment instanceof Enrollment || ! $enrollment->status->isLive()),
                'learn' => $enrollment instanceof Enrollment && in_array($enrollment->status, [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed], true),
                'leave' => $enrollment instanceof Enrollment && $enrollment->status->isLive() && in_array($enrollment->source->value, ['self', 'path'], true),
                'manage' => Gate::allows('training.manage'),
            ],
        ];
    }
}
