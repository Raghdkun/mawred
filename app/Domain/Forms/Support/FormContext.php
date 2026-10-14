<?php

declare(strict_types=1);

namespace App\Domain\Forms\Support;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Support\BranchHolidays;
use App\Support\Time\LocalClock;
use Illuminate\Database\Eloquent\Model;

/**
 * The rules context a form is filled in (ADR-0008): who is asking (`requester.*`) and `ctx.*`
 * (today on the requester's branch, its calendar for working-day maths, leave balances once
 * Phase 5 ships). The same shape is sent to the client so both sides evaluate alike.
 */
final readonly class FormContext
{
    public function __construct(private LocalClock $clock, private BranchHolidays $holidays) {}

    /**
     * @return array{requester: array<string, string|null>|null, ctx: array<string, mixed>}
     */
    public function for(?User $user): array
    {
        $employee = $user instanceof User ? Employee::query()->where('user_id', $user->id)->first() : null;

        return $this->forEmployee($employee);
    }

    /**
     * @return array{requester: array<string, string|null>|null, ctx: array<string, mixed>}
     */
    public function forEmployee(?Employee $employee): array
    {
        $branch = $employee?->current_branch_id === null ? null : Branch::withTrashed()->find($employee->current_branch_id);
        $timezone = $branch instanceof Branch ? $branch->timezone : FieldSpec::string(config('app.timezone'), 'UTC');
        $today = $this->clock->today($timezone);
        $year = (int) substr($today, 0, 4);

        return [
            'requester' => $employee instanceof Employee ? [
                'id' => $employee->ulid,
                'branch' => $branch?->ulid,
                'department' => $this->ulid(Department::class, $employee->current_department_id),
                'position' => $this->ulid(Position::class, $employee->current_position_id),
                'grade' => $this->ulid(Grade::class, $employee->current_grade_id),
                'employment_type' => $this->ulid(EmploymentType::class, $employee->current_employment_type_id),
                'manager' => $this->ulid(Employee::class, $employee->current_manager_id),
                'gender' => $employee->gender,
                'hire_date' => $employee->hire_date->toDateString(),
                'currency' => $branch?->default_currency,
            ] : null,
            'ctx' => [
                'today' => $today,
                'calendar' => [
                    'weekend' => $branch instanceof Branch ? array_map(intval(...), $branch->weekend_days) : [6, 7],
                    'holidays' => $branch instanceof Branch ? $this->holidays->dates($branch, ($year - 1).'-01-01', ($year + 1).'-12-31') : [],
                ],
                // Leave balances arrive with the leave ledger (Phase 5).
                'balances' => (object) [],
            ],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function ulid(string $model, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $ulid = $model::query()->withoutGlobalScopes()->whereKey($id)->value('ulid');

        return is_string($ulid) ? $ulid : null;
    }
}
