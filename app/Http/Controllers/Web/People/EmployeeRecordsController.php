<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Models\User;
use App\Domain\People\Actions\AssignAsset;
use App\Domain\People\Actions\InviteEmployee;
use App\Domain\People\Actions\RecordSalaryChange;
use App\Domain\People\Actions\ReturnAsset;
use App\Domain\People\Actions\RevokeInvitation;
use App\Domain\People\Models\AssetAssignment;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\UserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Smaller profile records: salary changes, assets and account invitations.
 */
final class EmployeeRecordsController extends Controller
{
    public function storeSalary(Request $request, Employee $employee, RecordSalaryChange $record, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'effectiveOn' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $record->handle(
            Actor::user($user),
            $employee,
            Money::fromDecimal($request->string('amount')->toString(), mb_strtoupper($request->string('currency')->toString())),
            $request->string('effectiveOn')->toString(),
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return $this->done(__('Salary change recorded.'));
    }

    public function storeAsset(Request $request, Employee $employee, AssignAsset $assign): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'assetTag' => ['required', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:40'],
            'serialNumber' => ['nullable', 'string', 'max:80'],
            'assignedOn' => ['required', 'date_format:Y-m-d'],
            'dueBackOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:assignedOn'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $assign->handle(
            $employee,
            $request->string('name')->toString(),
            $request->string('assetTag')->toString(),
            $request->string('assignedOn')->toString(),
            $request->filled('category') ? $request->string('category')->toString() : null,
            $request->filled('serialNumber') ? $request->string('serialNumber')->toString() : null,
            $request->filled('dueBackOn') ? $request->string('dueBackOn')->toString() : null,
            $request->filled('notes') ? $request->string('notes')->toString() : null,
        );

        return $this->done(__('Asset handed over.'));
    }

    public function returnAsset(Request $request, Employee $employee, AssetAssignment $asset, ReturnAsset $return): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        abort_unless($asset->employee_id === $employee->id, 404);
        $request->validate(['returnedOn' => ['required', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:500']]);

        $return->handle($asset, $request->string('returnedOn')->toString(), $request->filled('notes') ? $request->string('notes')->toString() : null);

        return $this->done(__('Asset returned.'));
    }

    public function invite(Request $request, Employee $employee, InviteEmployee $invite, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        $request->validate(['email' => ['nullable', 'email', 'max:254'], 'locale' => ['nullable', Rule::in(SetLocale::SUPPORTED)]]);

        $invite->handle(
            Actor::user($user),
            $employee,
            $request->filled('email') ? $request->string('email')->toString() : null,
            $request->filled('locale') ? $request->string('locale')->toString() : null,
        );

        return $this->done(__('Invitation sent.'));
    }

    /**
     * Bulk "Invite to Mawred" from the directory: people who already have an account, no email,
     * or are outside the viewer's scope are skipped, never failed.
     */
    public function inviteMany(Request $request, InviteEmployee $invite, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('people.update');
        $request->validate(['employees' => ['required', 'array', 'max:200'], 'employees.*' => ['string']]);

        $sent = 0;
        $skipped = 0;

        foreach (Employee::query()->whereIn('ulid', $request->array('employees'))->get() as $employee) {
            if (! Gate::allows('update', $employee)) {
                $skipped++;

                continue;
            }

            try {
                $invite->handle(Actor::user($user), $employee);
                $sent++;
            } catch (DomainRuleViolation) {
                $skipped++;
            }
        }

        return $this->done(__('Invitations: :sent sent, :skipped skipped.', ['sent' => $sent, 'skipped' => $skipped]));
    }

    public function revokeInvitation(Employee $employee, RevokeInvitation $revoke): RedirectResponse
    {
        $this->authorizeUpdate($employee);

        UserInvitation::query()->where('employee_id', $employee->id)->whereNull('accepted_at')->whereNull('revoked_at')
            ->get()->each(fn (UserInvitation $invitation) => $revoke->handle($invitation));

        return $this->done(__('Invitation cancelled.'));
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
