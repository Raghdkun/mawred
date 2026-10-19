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
use App\Support\Time\LocalClock;

/**
 * Enrols everyone in a mandatory course's audience who isn't on it yet and hasn't completed it
 * (D100), each with a due date counted from their own branch-local today. Safe to re-run:
 * people already enrolled are skipped. Leaving the audience doesn't cancel an enrolment.
 */
final readonly class ApplyCourseAudience
{
    public function __construct(private Enrol $enrol, private LearnerClock $learner, private LocalClock $clock) {}

    public function handle(Course $course): int
    {
        $audience = CourseAudience::from($course->audience);

        if ($course->status !== CourseStatus::Published || ! $audience instanceof CourseAudience) {
            return 0;
        }

        $actor = Actor::system('training:audience');
        $count = 0;
        $taken = Enrollment::query()->select('employee_id')->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Requested->value, EnrollmentStatus::Enrolled->value, EnrollmentStatus::Completed->value]);

        $audience->employees($this->clock->today(config()->string('app.timezone')))->whereNotIn('id', $taken)->orderBy('id')
            ->each(function (Employee $employee) use ($actor, $course, &$count): void {
                $this->enrol->handle($actor, $employee, $course, EnrollmentSource::Audience, $this->learner->dueOn($employee, $course->due_days));
                $count++;
            });

        return $count;
    }
}
