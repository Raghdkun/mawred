<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Data\Values\WeekendValue;
use App\Domain\Policies\Enums\PolicySection;

/** Weekend days; the branch's own weekend unless a policy set overrides it (D46). */
final class WeekendKey extends PolicyKey
{
    public function id(): string
    {
        return 'work.weekend';
    }

    public function section(): PolicySection
    {
        return PolicySection::WorkWeek;
    }

    public function valueClass(): string
    {
        return WeekendValue::class;
    }

    public function default(): WeekendValue
    {
        return new WeekendValue([6, 7]);
    }

    public function affects(): array
    {
        return ['leave', 'scheduling', 'attendance'];
    }

    public function hasBranchFallback(): bool
    {
        return true;
    }

    public function branchFallback(Branch $branch): WeekendValue
    {
        return new WeekendValue(array_map(intval(...), $branch->weekend_days));
    }
}
