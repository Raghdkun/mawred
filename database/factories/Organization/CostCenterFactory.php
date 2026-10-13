<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Domain\Organization\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
final class CostCenterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'code' => 'CC-'.fake()->unique()->numerify('###'),
            'name' => ['en' => ucfirst($word), 'ar' => $word],
        ];
    }
}
