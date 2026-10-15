<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Data\HolidayMarkData;
use App\Domain\Leave\Data\LeaveEventData;
use App\Domain\Leave\Queries\LeaveCalendarQuery;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\HolidayOccurrence;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The leave calendar (handoff 24): time away in a date range for the people the viewer can see,
 * filtered by branch or department (with the departments under it), plus that branch's holidays.
 */
final class LeaveCalendarController extends Controller
{
    public function __invoke(Request $request, LeaveCalendarQuery $query, BranchHolidays $holidays): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch' => ['nullable', 'string', 'size:26'],
            'department' => ['nullable', 'string', 'size:26'],
        ]);
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        abort_if(CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 62, 422, __('Choose at most two months.'));

        $branch = $request->filled('branch') ? Branch::query()->where('ulid', $request->string('branch')->toString())->firstOrFail() : null;
        $department = $request->filled('department') ? Department::query()->where('ulid', $request->string('department')->toString())->firstOrFail() : null;
        $departmentIds = null;

        if ($department instanceof Department) {
            $departmentIds = [];

            foreach (Department::query()->withinSubtree($department)->get(['id']) as $node) {
                $departmentIds[] = $node->id;
            }
        }

        $events = $query->between($from, $to)->inBranch($branch?->id)->inDepartments($departmentIds)
            ->visibleTo($user)
            ->with('request')
            ->limit(2000)
            ->get()
            ->map(LeaveEventData::fromModel(...))
            ->all();

        $holidayBranch = $branch ?? $this->ownBranch($user);
        $marks = $holidayBranch instanceof Branch
            ? array_map(fn (HolidayOccurrence $o): HolidayMarkData => new HolidayMarkData($o->observedOn, $o->name), $holidays->between($holidayBranch, $from, $to))
            : [];

        return response()->json(['data' => array_values($events), 'meta' => ['from' => $from, 'to' => $to, 'holidays' => $marks, 'weekend' => $holidayBranch instanceof Branch ? $holidayBranch->weekend_days : [6, 7]]]);
    }

    private function ownBranch(User $user): ?Branch
    {
        $branchId = Employee::query()->where('user_id', $user->id)->value('current_branch_id');

        return is_int($branchId) ? Branch::query()->find($branchId) : null;
    }
}
