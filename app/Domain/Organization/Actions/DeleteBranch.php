<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Branch;

/**
 * Archives a branch (soft delete). History keeps pointing at it.
 */
final readonly class DeleteBranch
{
    public function handle(Branch $branch): void
    {
        $branch->delete();
    }
}
