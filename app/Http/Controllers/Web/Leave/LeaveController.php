<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Leave;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Leave\Support\LeaveYear;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My leave (balances, statements, my time away) and the leave calendar (handoff 24). Both read
 * through /api/v1 so the profile tab and the mobile app share the same answers.
 */
final class LeaveController extends Controller
{
    public function index(#[CurrentUser] User $user, EmployeeDays $days): Response
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();

        return Inertia::render('leave/index', [
            'employeeId' => $employee?->ulid,
            'year' => $employee instanceof Employee ? LeaveYear::of($days->today($employee)) : (int) now()->format('Y'),
            'can' => ['configure' => Gate::allows('leave.configure')],
        ]);
    }

    public function calendar(#[CurrentUser] User $user, EmployeeDays $days): Response
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();
        $branch = $employee instanceof Employee ? $days->branch($employee) : null;

        return Inertia::render('leave/calendar', [
            'today' => $employee instanceof Employee ? $days->today($employee) : now()->toDateString(),
            'branch' => $branch instanceof Branch ? ['id' => $branch->ulid, 'name' => $branch->getTranslations('name')] : null,
            'can' => ['filter' => Gate::allows('leave.view'), 'configure' => Gate::allows('leave.configure')],
        ]);
    }
}
