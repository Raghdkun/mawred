<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Domain\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
final class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'code' => 'BR-'.fake()->unique()->numerify('####'),
            'name' => ['en' => "{$city} Branch", 'ar' => "فرع {$city}"],
            'address' => ['city' => $city],
            'country_code' => 'US',
            'timezone' => 'America/New_York',
            'default_currency' => 'USD',
            'phone_region' => 'US',
            'weekend_days' => [6, 7],
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
        ];
    }

    public function riyadh(): self
    {
        return $this->state(fn (): array => [
            'country_code' => 'SA',
            'timezone' => 'Asia/Riyadh',
            'default_currency' => 'SAR',
            'phone_region' => 'SA',
            'weekend_days' => [5, 6],
        ]);
    }

    public function withGeofence(float $lat = 24.7136, float $lng = 46.6753, int $radius = 150): self
    {
        return $this->state(fn (): array => ['geofence_lat' => $lat, 'geofence_lng' => $lng, 'geofence_radius_m' => $radius]);
    }
}
