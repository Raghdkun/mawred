<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Workflows\Actions\EndDelegation;
use App\Domain\Workflows\Actions\SaveDelegation;
use App\Domain\Workflows\Models\Delegation;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Time\LocalClock;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "While I'm away": a person's own approval delegations (ADR-0009). */
final class DelegationsController extends Controller
{
    public function index(#[CurrentUser] User $user, LocalClock $clock): Response
    {
        $today = $clock->today($user->timezone ?? 'UTC');

        return Inertia::render('approvals/delegations', [
            'delegations' => Delegation::query()->with('to')->where('from_user_id', $user->id)->where('ends_on', '>=', $today)->oldest('starts_on')->get()
                ->map(fn (Delegation $d): array => [
                    'id' => $d->ulid,
                    'to' => $d->to?->name,
                    'startsOn' => $d->starts_on->toDateString(),
                    'endsOn' => $d->ends_on->toDateString(),
                    'reason' => $d->reason,
                    'active' => $d->starts_on->toDateString() <= $today,
                ])->all(),
            'today' => $today,
        ]);
    }

    public function store(Request $request, SaveDelegation $save, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate([
            'to' => ['required', 'string', 'exists:users,ulid'],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $to = User::query()->where('ulid', $request->string('to')->toString())->where('status', 'active')->firstOrFail();
        $save->handle(Actor::user($user), $user, $to, $request->string('startsOn')->toString(), $request->string('endsOn')->toString(), $request->filled('reason') ? $request->string('reason')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('New approvals will go to :name on those dates.', ['name' => $to->name])]);

        return back();
    }

    public function destroy(Delegation $delegation, EndDelegation $end, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($delegation->from_user_id === $user->id, 404);
        $end->handle(Actor::user($user), $delegation);

        return back();
    }
}
