<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Actions\AdjustLeaveBalance;
use App\Domain\Leave\Actions\CancelApprovedLeave;
use App\Domain\Leave\Data\LeaveEventData;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Support\LeaveBalances;
use App\Domain\Leave\Support\LeaveQuotes;
use App\Domain\Leave\Support\LeaveYear;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Leave over /api/v1 (Phase 5a): the live quote on the request form, balances and statements,
 * someone's time away, balance adjustments and cancelling approved leave. Out-of-scope people
 * answer 404.
 */
final class LeaveController extends Controller
{
    public function quote(Request $request, RequestType $type, LeaveQuotes $quotes): JsonResponse
    {
        $user = $request->user();
        $employee = $user instanceof User ? Employee::query()->where('user_id', $user->id)->first() : null;
        abort_unless($employee instanceof Employee && $type->isAvailable() && $type->behaviour !== null, 404);

        $request->validate(['values' => ['present', 'array'], 'request' => ['nullable', 'string', 'size:26']]);
        $except = null;

        if ($request->filled('request')) {
            $returned = EmployeeRequest::query()->where('ulid', $request->string('request')->toString())->where('requester_employee_id', $employee->id)->where('status', RequestStatus::Returned->value)->first();
            $except = $returned instanceof EmployeeRequest ? LeaveRequest::query()->where('request_id', $returned->id)->value('id') : null;
        }

        $values = [];

        foreach ($request->array('values') as $key => $value) {
            $values[(string) $key] = $value;
        }

        return response()->json(['data' => $quotes->quote($employee, $type, $values, is_int($except) ? $except : null)]);
    }

    public function balances(Request $request, Employee $employee, LeaveBalances $balances): JsonResponse
    {
        abort_unless(Gate::allows('viewLeave', $employee), 404);
        $year = $this->year($request);

        return response()->json([
            'data' => $balances->all($employee, $year),
            'meta' => ['year' => $year, 'canAdjust' => Gate::allows('adjustLeave', $employee)],
        ]);
    }

    public function statement(Request $request, Employee $employee, LeaveType $leaveType, LeaveBalances $balances): JsonResponse
    {
        abort_unless(Gate::allows('viewLeave', $employee), 404);
        $year = $this->year($request);

        return response()->json(['data' => $balances->statement($employee, $leaveType, $year), 'meta' => ['year' => $year]]);
    }

    public function timeAway(Request $request, Employee $employee): JsonResponse
    {
        abort_unless(Gate::allows('viewLeave', $employee), 404);
        $year = $this->year($request);

        $events = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'request'])
            ->where('employee_id', $employee->id)
            ->whereBetween('starts_on', [LeaveYear::start($year), LeaveYear::end($year)])
            ->latest('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(LeaveEventData::fromModel(...))
            ->all();

        return response()->json(['data' => array_values($events), 'meta' => ['year' => $year]]);
    }

    public function adjust(Request $request, Employee $employee, AdjustLeaveBalance $adjust): JsonResponse
    {
        abort_unless(Gate::allows('viewLeave', $employee), 404);
        Gate::authorize('adjustLeave', $employee);

        $request->validate([
            'leaveType' => ['required', 'string', 'size:26'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'amount' => ['required', 'numeric', 'between:-365,365', 'not_in:0'],
            'note' => ['required', 'string', 'max:300'],
        ]);

        $type = LeaveType::withTrashed()->where('ulid', $request->string('leaveType')->toString())->firstOrFail();
        $entry = $adjust->handle($this->actor($request), $employee, $type, $request->integer('year'), $request->float('amount'), $request->string('note')->toString());

        return response()->json(['data' => ['id' => $entry->ulid]], 201);
    }

    public function cancel(Request $request, LeaveRequest $leave, CancelApprovedLeave $cancel): JsonResponse
    {
        $employee = $leave->employee()->first();
        abort_unless($employee instanceof Employee && Gate::allows('viewLeave', $employee), 404);
        Gate::authorize('cancel', $leave);

        $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $cancel->handle($this->actor($request), $leave, $request->string('reason')->toString());

        return response()->json(['data' => LeaveEventData::fromModel($leave->fresh(['employee', 'leaveType', 'request']) ?? $leave)]);
    }

    private function actor(Request $request): Actor
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Actor::user($user, Channel::Api);
    }

    private function year(Request $request): int
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);

        return $request->integer('year', (int) now()->format('Y'));
    }
}
