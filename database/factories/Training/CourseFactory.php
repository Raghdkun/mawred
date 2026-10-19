<?php

declare(strict_types=1);

namespace Database\Factories\Training;

use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A published course with no modules. Tests add modules through `SaveCourseModules`.
 *
 * @extends Factory<Course>
 */
final class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(3), '.');

        return [
            'code' => 'TRN-'.fake()->unique()->numerify('#####'),
            'title' => ['en' => $title, 'ar' => "دورة {$title}"],
            'summary' => ['en' => fake()->sentence(), 'ar' => 'ملخص الدورة'],
            'category' => 'skills',
            'status' => CourseStatus::Published,
            'published_at' => now(),
        ];
    }

    public function draft(): self
    {
        return $this->state(fn (): array => ['status' => CourseStatus::Draft, 'published_at' => null]);
    }
}
