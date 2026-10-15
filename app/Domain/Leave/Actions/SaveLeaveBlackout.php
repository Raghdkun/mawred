<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Data\LeaveBlackoutInputData;
use App\Domain\Leave\Models\LeaveBlackout;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Policies\Models\LeaveType;
use App\Support\Actors\Actor;

/**
 * A blackout period: dates when leave can't be requested. Requests already sent stand; new ones
 * are refused (D65).
 */
final readonly class SaveLeaveBlackout
{
    public function handle(Actor $actor, LeaveBlackoutInputData $input, ?LeaveBlackout $blackout = null): LeaveBlackout
    {
        $blackout ??= new LeaveBlackout(['created_by' => $actor->user?->id]);
        $typeIds = [];

        foreach (LeaveType::withTrashed()->whereIn('ulid', $input->leaveTypeIds)->get(['id']) as $type) {
            $typeIds[] = $type->id;
        }

        $blackout->setTranslations('name', ReferenceValues::translations($input->name));
        $blackout->fill([
            'starts_on' => $input->startsOn,
            'ends_on' => $input->endsOn,
            'branch_id' => $input->branchId === null ? null : Branch::query()->where('ulid', $input->branchId)->value('id'),
            'department_id' => $input->departmentId === null ? null : Department::query()->where('ulid', $input->departmentId)->value('id'),
            'leave_type_ids' => $typeIds === [] ? null : $typeIds,
        ])->save();

        return $blackout;
    }
}
