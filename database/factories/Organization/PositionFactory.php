<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Domain\Organization\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
final class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->jobTitle();

        return [
            'code' => 'POS-'.fake()->unique()->numerify('####'),
            'title' => ['en' => $title, 'ar' => "وظيفة {$title}"],
        ];
    }
}
