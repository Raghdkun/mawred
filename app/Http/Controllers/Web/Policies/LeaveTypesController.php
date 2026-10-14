<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Policies;

use App\Domain\Policies\Actions\DeleteLeaveType;
use App\Domain\Policies\Actions\SaveLeaveType;
use App\Domain\Policies\Data\LeaveTypeData;
use App\Domain\Policies\Data\LeaveTypeInputData;
use App\Domain\Policies\Models\LeaveType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Leave type catalogue (D48). Each type gets a `leave.type.<CODE>` key in the policy editor.
 */
final class LeaveTypesController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('policies.view');

        return Inertia::render('policies/leave-types', [
            'types' => LeaveType::query()->orderBy('sort_order')->orderBy('id')->get()->map(LeaveTypeData::fromType(...)),
            'can' => ['configure' => Gate::allows('policies.configure')],
        ]);
    }

    public function store(LeaveTypeInputData $data, SaveLeaveType $save): RedirectResponse
    {
        Gate::authorize('policies.configure');

        $save->handle($data);

        return $this->saved(__('Leave type saved.'));
    }

    public function update(LeaveTypeInputData $data, LeaveType $type, SaveLeaveType $save): RedirectResponse
    {
        Gate::authorize('policies.configure');

        $save->handle($data, $type);

        return $this->saved(__('Leave type saved.'));
    }

    public function destroy(LeaveType $type, DeleteLeaveType $delete): RedirectResponse
    {
        Gate::authorize('policies.configure');

        $delete->handle($type);

        return $this->saved(__('Leave type archived.'));
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
