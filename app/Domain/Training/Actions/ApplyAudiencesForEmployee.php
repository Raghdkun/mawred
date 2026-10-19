<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Support\CourseAudience;
use App\Domain\Training\Support\LearnerClock;
use App\Support\Actors\Actor;

/**
 * When someone joins or moves (D100): enrols them in every mandatory course whose audience now
 * includes them, unless they're on it already or have completed it.
 */
final readonly class ApplyAudiencesForEmployee
{
    public function __construct(private Enrol $enrol, private LearnerClock $learner) {}

    public function handle(Employee $employee): int
    {
        $today = $this->learner->today($employee);
        $count = 0;
        $taken = Enrollment::query()->where('employee_id', $employee->id)
            ->whereIn('status', [EnrollmentStatus::Requested->value, EnrollmentStatus::Enrolled->value, EnrollmentStatus::Completed->value])
            ->pluck('course_id');

        foreach (Course::query()->where('status', CourseStatus::Published->value)->whereNotNull('audience')->whereNotIn('id', $taken)->get() as $course) {
            $audience = CourseAudience::from($course->audience);

            if ($audience instanceof CourseAudience && $audience->includes($employee, $today)) {
                $this->enrol->handle(Actor::system('training:audience'), $employee, $course, EnrollmentSource::Audience, $this->learner->dueOn($employee, $course->due_days));
                $count++;
            }
        }

        return $count;
    }
}
