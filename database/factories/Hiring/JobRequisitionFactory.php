<?php

declare(strict_types=1);

namespace Database\Factories\Hiring;

use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open requisition. Tests that need its pipeline open it through `OpenRequisition`.
 *
 * @extends Factory<JobRequisition>
 */
final class JobRequisitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'number' => 'JR-'.fake()->unique()->numerify('#####'),
            'title' => ['en' => $title, 'ar' => "وظيفة {$title}"],
            'branch_id' => Branch::factory(),
            'headcount' => 1,
            'status' => RequisitionStatus::Open,
            'opened_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => ['status' => RequisitionStatus::Pending, 'opened_at' => null]);
    }
}
