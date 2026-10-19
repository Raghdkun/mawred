<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Support\Actors\Actor;

/**
 * A trainer or HR enrols people on a course, with an optional due date. No approval: whoever
 * assigns is deciding. People already on it keep their enrolment (the due date can move closer).
 */
final readonly class AssignCourse
{
    public function __construct(private Enrol $enrol) {}

    /**
     * @param  iterable<Employee>  $employees
     * @return list<Enrollment>
     */
    public function handle(Actor $actor, Course $course, iterable $employees, ?string $dueOn = null): array
    {
        $enrollments = [];

        foreach ($employees as $employee) {
            $enrollments[] = $this->enrol->handle($actor, $employee, $course, EnrollmentSource::Assigned, $dueOn);
        }

        return $enrollments;
    }
}
