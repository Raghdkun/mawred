<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Scheduling\Actions\ClaimOpenShift;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Support\MySchedule;
use App\Domain\Scheduling\Support\RosterAccess;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** My schedule (handoff 23): my published shifts by month, open shifts to claim, swaps. */
final class MyScheduleController extends Controller
{
    public function show(Request $request, #[CurrentUser] User $user, EmployeeDays $days, RosterAccess $access): Response
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $employee = Employee::query()->where('user_id', $user->id)->first();
        $today = $employee instanceof Employee ? $days->today($employee) : now()->toDateString();
        $month = CarbonImmutable::parse($request->string('month', substr($today, 0, 7))->toString().'-01');
        $swap = RequestType::query()->where('code', 'SHIFT_SWAP')->first();

        return Inertia::render('schedule/mine', [
            'employeeId' => $employee?->ulid,
            'today' => $today,
            'month' => $month->format('Y-m'),
            'shifts' => $employee instanceof Employee ? MySchedule::shifts($employee, $month->toDateString(), $month->endOfMonth()->toDateString()) : [],
            'openShifts' => $employee instanceof Employee ? MySchedule::openShifts($employee, $today) : [],
            'swapTypeId' => $swap instanceof RequestType && $swap->isAvailable() ? $swap->ulid : null,
            'can' => ['plan' => $access->branches($user, $today) !== []],
        ]);
    }

    public function claim(#[CurrentUser] User $user, RosterShift $shift, ClaimOpenShift $claim, EmployeeDays $days): RedirectResponse
    {
        $employee = Employee::query()->where('user_id', $user->id)->firstOrFail();
        $claim->handle($employee, $shift, $days->today($employee));
        Inertia::flash('toast', ['type' => 'success', 'message' => $shift->fresh()?->employee_id === $employee->id ? __('The open shift is yours') : __('Claim sent. You’ll hear when it’s decided.')]);

        return back();
    }
}
