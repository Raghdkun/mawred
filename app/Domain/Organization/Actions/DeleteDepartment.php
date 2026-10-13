<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use App\Support\Errors\DomainRuleViolation;

/**
 * Archives an empty department. Sub-departments and positions must be moved or archived first,
 * so nothing is left pointing at a hidden node.
 */
final readonly class DeleteDepartment
{
    public function handle(Department $department): void
    {
        if ($department->children()->exists()) {
            throw new DomainRuleViolation('organization.department_has_children', __('Move or archive the sub-departments first.'));
        }

        if (Position::query()->where('department_id', $department->id)->exists()) {
            throw new DomainRuleViolation('organization.department_has_positions', __('Move or archive the positions in this department first.'));
        }

        $department->delete();
    }
}
