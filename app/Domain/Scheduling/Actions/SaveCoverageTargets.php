<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\Scheduling\Models\CoverageTarget;

/** How many people a branch needs each weekday (0 removes the target). */
final readonly class SaveCoverageTargets
{
    /**
     * @param  array<int, int>  $required  ISO weekday => people
     */
    public function handle(Branch $branch, array $required): void
    {
        foreach ($required as $weekday => $count) {
            if ($count <= 0) {
                CoverageTarget::query()->where('branch_id', $branch->id)->where('weekday', $weekday)->delete();

                continue;
            }

            CoverageTarget::query()->updateOrCreate(['branch_id' => $branch->id, 'weekday' => $weekday], ['required' => $count]);
        }
    }
}
