<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Data\Values\WorkHoursValue;
use App\Domain\Policies\Enums\PolicySection;

/** Standard working hours; the branch's hours unless a policy set overrides them (D46). */
final class WorkHoursKey extends PolicyKey
{
    public function id(): string
    {
        return 'work.hours';
    }

    public function section(): PolicySection
    {
        return PolicySection::WorkWeek;
    }

    public function valueClass(): string
    {
        return WorkHoursValue::class;
    }

    public function default(): WorkHoursValue
    {
        return new WorkHoursValue('09:00', '17:00', 60);
    }

    public function affects(): array
    {
        return ['scheduling', 'attendance', 'overtime'];
    }

    public function hasBranchFallback(): bool
    {
        return true;
    }

    public function branchFallback(Branch $branch): WorkHoursValue
    {
        return new WorkHoursValue(substr($branch->work_starts_at, 0, 5), substr($branch->work_ends_at, 0, 5), 60);
    }
}
