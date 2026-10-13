<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Domain\Organization\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
final class GradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'code' => 'G-'.fake()->unique()->numerify('###'),
            'name' => ['en' => ucfirst($word), 'ar' => $word],
        ];
    }
}
