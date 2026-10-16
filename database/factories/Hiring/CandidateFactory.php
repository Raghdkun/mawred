<?php

declare(strict_types=1);

namespace Database\Factories\Hiring;

use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
final class CandidateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9665'.fake()->numerify('########'),
            'source' => ApplicationSource::Careers,
            'consented_at' => now(),
            'last_activity_at' => now(),
        ];
    }
}
