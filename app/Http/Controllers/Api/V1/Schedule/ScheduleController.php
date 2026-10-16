<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Schedule;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Actions\ClaimOpenShift;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\MySchedule;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** My schedule over /api/v1 (handoff 23): published shifts in a range and open shifts to claim. */
final class ScheduleController extends Controller
{
    public function index(Request $request, EmployeeDays $days): JsonResponse
    {
        $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $employee = $this->employee($request);

        return response()->json([
            'data' => MySchedule::shifts($employee, $request->string('from')->toString(), $request->string('to')->toString()),
            'meta' => ['open_shifts' => MySchedule::openShifts($employee, $days->today($employee))],
        ]);
    }

    public function claim(Request $request, RosterShift $shift, ClaimOpenShift $claim, EmployeeDays $days): JsonResponse
    {
        $employee = $this->employee($request);
        $result = $claim->handle($employee, $shift, $days->today($employee));

        return response()->json(['data' => ['id' => $result->ulid, 'status' => $result->status->value, 'assigned' => $shift->fresh()?->employee_id === $employee->id]], 201);
    }

    private function employee(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? Employee::query()->where('user_id', $user->id)->first() : null;
        abort_unless($employee instanceof Employee, 404);

        return $employee;
    }
}
