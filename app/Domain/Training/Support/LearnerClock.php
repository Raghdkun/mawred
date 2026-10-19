<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;

/** "Today" where the learner works (ADR-0004): due dates and overdue checks are branch-local. */
final readonly class LearnerClock
{
    public function __construct(private LocalClock $clock) {}

    /** The learner's branch-local today; the company's when there is no learner. */
    public function today(?Employee $employee): string
    {
        return $this->clock->today($employee instanceof Employee ? $this->timezone($employee) : FieldSpec::string(config('app.timezone'), 'UTC'));
    }

    public function dueOn(Employee $employee, ?int $days): ?string
    {
        return $days === null ? null : CarbonImmutable::parse($this->today($employee))->addDays($days)->toDateString();
    }

    public function timezone(Employee $employee): string
    {
        $timezone = $employee->current_branch_id === null ? null : Branch::withTrashed()->whereKey($employee->current_branch_id)->value('timezone');

        return is_string($timezone) ? $timezone : FieldSpec::string(config('app.timezone'), 'UTC');
    }
}
