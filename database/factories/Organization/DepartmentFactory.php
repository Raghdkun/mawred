<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Domain\Organization\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds the ltree path after insert, as SaveDepartment does.
 *
 * @extends Factory<Department>
 */
final class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => 'DEP-'.fake()->unique()->numerify('####'),
            'name' => ['en' => $name, 'ar' => "قسم {$name}"],
        ];
    }

    public function childOf(Department $parent): self
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    public function configure(): self
    {
        return $this->afterCreating(function (Department $department): void {
            $parentPath = $department->parent_id === null ? null : Department::query()->whereKey($department->parent_id)->value('path');
            $path = is_string($parentPath) ? "{$parentPath}.{$department->id}" : (string) $department->id;

            Department::query()->whereKey($department->id)->update(['path' => $path, 'depth' => substr_count($path, '.')]);
            $department->refresh();
        });
    }
}
