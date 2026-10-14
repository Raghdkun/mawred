<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Models\HolidayRule;
use App\Domain\Policies\Support\BranchHolidays;

final readonly class DeleteHolidayRule
{
    public function __construct(private BranchHolidays $holidays) {}

    public function handle(HolidayRule $rule): void
    {
        $rule->delete();
        $this->holidays->forget();
    }
}
