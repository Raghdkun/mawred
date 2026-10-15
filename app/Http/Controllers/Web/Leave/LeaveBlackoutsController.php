<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Leave;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Actions\DeleteLeaveBlackout;
use App\Domain\Leave\Actions\SaveLeaveBlackout;
use App\Domain\Leave\Data\LeaveBlackoutData;
use App\Domain\Leave\Data\LeaveBlackoutInputData;
use App\Domain\Leave\Models\LeaveBlackout;
use App\Domain\Policies\Models\LeaveType;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Blackout periods (leave.configure): dates when leave can't be requested. */
final class LeaveBlackoutsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('leave.configure');
        $types = [];

        foreach (LeaveType::withTrashed()->orderBy('sort_order')->get() as $type) {
            $types[$type->id] = $type;
        }

        return Inertia::render('leave/blackouts', [
            'blackouts' => LeaveBlackout::query()->with(['branch', 'department'])->latest('starts_on')->get()
                ->map(fn (LeaveBlackout $b): LeaveBlackoutData => LeaveBlackoutData::fromModel($b, $types))->values()->all(),
            'leaveTypes' => array_values(array_map(fn (LeaveType $t): array => ['id' => $t->ulid, 'name' => $t->getTranslations('name')], array_filter($types, fn (LeaveType $t): bool => $t->is_active && ! $t->trashed()))),
        ]);
    }

    public function store(#[CurrentUser] User $user, LeaveBlackoutInputData $input, SaveLeaveBlackout $save): RedirectResponse
    {
        Gate::authorize('leave.configure');
        $save->handle(Actor::user($user), $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Blackout period saved.')]);

        return back();
    }

    public function update(#[CurrentUser] User $user, LeaveBlackout $blackout, LeaveBlackoutInputData $input, SaveLeaveBlackout $save): RedirectResponse
    {
        Gate::authorize('leave.configure');
        $save->handle(Actor::user($user), $input, $blackout);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Blackout period saved.')]);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, LeaveBlackout $blackout, DeleteLeaveBlackout $delete): RedirectResponse
    {
        Gate::authorize('leave.configure');
        $delete->handle(Actor::user($user), $blackout);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Blackout period removed.')]);

        return back();
    }
}
