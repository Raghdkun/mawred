<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Domain\Attendance\Actions\RetireKiosk;
use App\Domain\Attendance\Actions\SaveKiosk;
use App\Domain\Attendance\Models\Kiosk;
use App\Domain\Organization\Models\Branch;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Kiosks (attendance.configure): screens at a branch that show rotating QR codes (D70). */
final class KiosksController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('attendance.configure');

        return Inertia::render('attendance/kiosks', [
            'kiosks' => array_values(Kiosk::query()->with('branch')->latest()->get()->map(fn (Kiosk $k): array => [
                'id' => $k->ulid,
                'name' => $k->name,
                'branch' => $k->branch?->getTranslations('name') ?? [],
                'active' => $k->is_active,
                'lastSeenAt' => $k->last_seen_at?->toIso8601String(),
            ])->all()),
            'branches' => array_values(Branch::query()->where('is_active', true)->orderBy('code')->get()->map(fn (Branch $b): array => ['id' => $b->ulid, 'name' => $b->getTranslations('name')])->all()),
        ]);
    }

    public function store(Request $request, SaveKiosk $save): RedirectResponse
    {
        Gate::authorize('attendance.configure');
        $request->validate(['name' => ['required', 'string', 'max:120'], 'branchId' => ['required', 'string', 'size:26', 'exists:branches,ulid']]);
        ['token' => $token] = $save->handle(Branch::query()->where('ulid', $request->string('branchId')->toString())->firstOrFail(), $request->string('name')->toString());
        Inertia::flash('kioskLink', route('kiosk.show', $token));

        return back();
    }

    public function relink(Kiosk $kiosk, SaveKiosk $save): RedirectResponse
    {
        Gate::authorize('attendance.configure');
        ['token' => $token] = $save->handle(Branch::query()->findOrFail($kiosk->branch_id), $kiosk->name, $kiosk);
        Inertia::flash('kioskLink', route('kiosk.show', $token));

        return back();
    }

    public function destroy(Kiosk $kiosk, RetireKiosk $retire): RedirectResponse
    {
        Gate::authorize('attendance.configure');
        $retire->handle($kiosk);

        return back();
    }
}
