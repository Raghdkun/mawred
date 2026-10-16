<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Calendar;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Support\ViewerZone;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meeting scheduler (handoff 27): pick people, see free/busy (D76) and suggested times, then book.
 * Availability comes from /api/v1/calendar/availability.
 */
final class FinderController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        Gate::authorize('calendar.create');
        $zone = ViewerZone::of($user);

        return Inertia::render('calendar/find', [
            'me' => ['id' => $user->ulid, 'name' => $user->name],
            'timezone' => $zone,
            'today' => CarbonImmutable::now($zone)->toDateString(),
        ]);
    }
}
