<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Models\User;
use App\Domain\People\Actions\SetCustomFieldValues;
use App\Domain\People\Actions\UpdateEmployeeProfile;
use App\Domain\People\Actions\UpdateSensitiveDetails;
use App\Domain\People\Data\EmployeeProfileInputData;
use App\Domain\People\Data\SensitiveDetailsInputData;
use App\Domain\People\Models\Employee;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Profile edits (screen 8 Overview / Personal): plain fields, sensitive groups (field
 * permissions apply inside the action) and custom fields.
 */
final class EmployeeProfileController extends Controller
{
    public function update(EmployeeProfileInputData $data, Employee $employee, UpdateEmployeeProfile $update): RedirectResponse
    {
        $this->authorizeUpdate($employee);

        $update->handle($employee, $data);

        return $this->saved();
    }

    public function updateSensitive(SensitiveDetailsInputData $data, Employee $employee, UpdateSensitiveDetails $update, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);

        $update->handle(Actor::user($user), $employee, $data);

        return $this->saved();
    }

    public function updateCustomFields(Request $request, Employee $employee, SetCustomFieldValues $set, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);

        $request->validate(['values' => ['present', 'array']]);

        /** @var array<string, mixed> $values */
        $values = $request->array('values');
        $set->handle(Actor::user($user), $employee, $values);

        return $this->saved();
    }

    private function authorizeUpdate(Employee $employee): void
    {
        abort_unless(Gate::allows('view', $employee), 404);
        Gate::authorize('update', $employee);
    }

    private function saved(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }
}
