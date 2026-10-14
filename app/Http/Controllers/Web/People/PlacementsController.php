<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Models\User;
use App\Domain\People\Actions\AddSecondaryAssignment;
use App\Domain\People\Actions\ChangePlacement;
use App\Domain\People\Actions\EndSecondaryAssignment;
use App\Domain\People\Actions\TerminateEmployee;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Job changes (screen 8 Job): transfer / promotion / manager change, secondary placements and
 * termination. Dates are branch-local calendar dates.
 */
final class PlacementsController extends Controller
{
    public function change(Request $request, PlacementInputData $placement, Employee $employee, ChangePlacement $change, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        $request->validate(['effectiveFrom' => ['required', 'date_format:Y-m-d'], 'reason' => ['nullable', 'string', 'max:300']]);

        $change->handle(Actor::user($user), $employee, $placement, $request->string('effectiveFrom')->toString(), $request->filled('reason') ? $request->string('reason')->toString() : null);

        return $this->done(__('Placement updated.'));
    }

    public function addSecondary(Request $request, PlacementInputData $placement, Employee $employee, AddSecondaryAssignment $add, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        $request->validate([
            'effectiveFrom' => ['required', 'date_format:Y-m-d'],
            'effectiveTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effectiveFrom'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $add->handle(
            Actor::user($user),
            $employee,
            $placement,
            $request->string('effectiveFrom')->toString(),
            $request->filled('effectiveTo') ? $request->string('effectiveTo')->toString() : null,
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        );

        return $this->done(__('Placement added.'));
    }

    public function endSecondary(Request $request, Employee $employee, EmployeeAssignment $assignment, EndSecondaryAssignment $end): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        abort_unless($assignment->employee_id === $employee->id, 404);
        $request->validate(['on' => ['required', 'date_format:Y-m-d']]);

        $end->handle($assignment, $request->string('on')->toString());

        return $this->done(__('Placement ended.'));
    }

    public function terminate(Request $request, Employee $employee, TerminateEmployee $terminate, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        $request->validate(['lastDay' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:300']]);

        $terminate->handle(Actor::user($user), $employee, $request->string('lastDay')->toString(), $request->string('reason')->toString());

        return $this->done(__('Termination recorded.'));
    }

    private function authorizeUpdate(Employee $employee): void
    {
        abort_unless(Gate::allows('view', $employee), 404);
        Gate::authorize('update', $employee);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
