<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Access;

use App\Domain\Access\Actions\StartImpersonation;
use App\Domain\Access\Actions\StopImpersonation;
use App\Domain\Access\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ApplyImpersonation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class ImpersonationController extends Controller
{
    public function store(Request $request, User $user, #[CurrentUser] User $admin, StartImpersonation $start): RedirectResponse
    {
        Gate::authorize('impersonate', $user);

        $start->handle($admin, $user);

        $request->session()->put(ApplyImpersonation::IMPERSONATOR, $admin->id);
        $request->session()->put(ApplyImpersonation::TARGET, $user->id);

        return to_route('home');
    }

    public function destroy(Request $request, StopImpersonation $stop): RedirectResponse
    {
        $impersonatorId = $request->session()->get(ApplyImpersonation::IMPERSONATOR);
        $targetId = $request->session()->get(ApplyImpersonation::TARGET);

        $request->session()->forget([ApplyImpersonation::IMPERSONATOR, ApplyImpersonation::TARGET]);

        $impersonator = is_int($impersonatorId) ? User::query()->find($impersonatorId) : null;

        if ($impersonator instanceof User) {
            $stop->handle($impersonator, is_int($targetId) ? User::query()->find($targetId) : null);
            Auth::guard('web')->setUser($impersonator);
        }

        return to_route('home');
    }
}
