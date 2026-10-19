<?php

declare(strict_types=1);

namespace Database\Factories\Training;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Someone enrolled in a course from the catalog.
 *
 * @extends Factory<Enrollment>
 */
final class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'course_id' => Course::factory(),
            'status' => EnrollmentStatus::Enrolled,
            'source' => EnrollmentSource::SelfService,
            'enrolled_at' => now(),
        ];
    }
}
