<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\LearningPath;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Joins a learning path: enrols the person in each of its courses they haven't completed.
 * Courses that need approval are requested, like enrolling from the catalog (D99).
 */
final readonly class JoinLearningPath
{
    public function __construct(private Enrol $enrol) {}

    /**
     * @return list<Enrollment>
     */
    public function handle(Actor $actor, Employee $employee, LearningPath $path): array
    {
        if ($path->status !== CourseStatus::Published) {
            throw new DomainRuleViolation('training.path_closed', __('This learning path isn’t open.'));
        }

        $completed = Enrollment::query()->where('employee_id', $employee->id)->where('status', EnrollmentStatus::Completed->value)->pluck('course_id')->all();
        $enrollments = [];

        foreach ($path->courses()->where('courses.status', CourseStatus::Published->value)->get() as $course) {
            if (! in_array($course->id, $completed, true)) {
                $enrollments[] = $this->enrol->handle($actor, $employee, $course, EnrollmentSource::Path, null, $path);
            }
        }

        return $enrollments;
    }
}
