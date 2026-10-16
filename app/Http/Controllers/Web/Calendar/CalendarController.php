<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Calendar;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Actions\CancelEvent;
use App\Domain\Calendar\Actions\RespondToEvent;
use App\Domain\Calendar\Actions\SaveEvent;
use App\Domain\Calendar\Data\EventInputData;
use App\Domain\Calendar\Enums\Response as Reply;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\EventIcs;
use App\Domain\Calendar\Support\MyCalendar;
use App\Domain\Calendar\Support\ViewerZone;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My calendar (week / month / agenda): meetings and bookings, shifts, time away and holidays,
 * plus creating, changing, cancelling and replying to meetings, and `.ics` downloads.
 */
final class CalendarController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, MyCalendar $calendar): Response
    {
        $request->validate(['view' => ['nullable', 'in:week,month'], 'date' => ['nullable', 'date_format:Y-m-d'], 'open' => ['nullable', 'string', 'max:80']]);
        $zone = ViewerZone::of($user);
        $today = CarbonImmutable::now($zone)->toDateString();
        $view = $request->string('view', 'week')->toString();
        $anchor = CarbonImmutable::parse($request->string('date', $today)->toString());
        [$from, $to] = $view === 'month'
            ? [$anchor->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY), $anchor->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY)]
            : [$anchor->startOfWeek(CarbonImmutable::SUNDAY), $anchor->startOfWeek(CarbonImmutable::SUNDAY)->addDays(6)];

        return Inertia::render('calendar/index', [
            ...$calendar->for($user, $from->toDateString(), $to->toDateString(), $zone),
            'view' => $view,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => $today,
            'timezone' => $zone,
            'open' => $request->string('open')->toString() ?: null,
            'me' => ['id' => $user->ulid, 'name' => $user->name],
            'can' => ['create' => Gate::allows('calendar.create'), 'bookingPages' => Gate::allows('calendar.update')],
        ]);
    }

    public function show(#[CurrentUser] User $user, CalendarEvent $event): RedirectResponse
    {
        abort_unless(Gate::allows('view', $event), 404);

        return to_route('calendar.index', ['date' => $event->starts_at->setTimezone(ViewerZone::of($user))->toDateString(), 'open' => $event->ulid]);
    }

    public function store(#[CurrentUser] User $user, EventInputData $input, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('calendar.create');
        $event = $save->handle(Actor::user($user), $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meeting saved and invitations sent.')]);

        return to_route('calendar.index', ['date' => $event->starts_at->setTimezone(ViewerZone::of($user))->toDateString()]);
    }

    public function update(Request $request, #[CurrentUser] User $user, CalendarEvent $event, EventInputData $input, SaveEvent $save): RedirectResponse
    {
        abort_unless(Gate::allows('view', $event), 404);
        Gate::authorize('update', $event);
        [$scope, $occurrence] = $this->scope($request);
        $save->handle(Actor::user($user), $input, $event, $scope, $occurrence);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meeting updated.')]);

        return back();
    }

    public function destroy(Request $request, #[CurrentUser] User $user, CalendarEvent $event, CancelEvent $cancel): RedirectResponse
    {
        abort_unless(Gate::allows('view', $event), 404);
        Gate::authorize('update', $event);
        [$scope, $occurrence] = $this->scope($request);
        $cancel->handle(Actor::user($user), $event, $scope, $occurrence);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meeting cancelled. Everyone on it was told.')]);

        return back();
    }

    public function respond(Request $request, #[CurrentUser] User $user, CalendarEvent $event, RespondToEvent $respond): RedirectResponse
    {
        abort_unless(Gate::allows('view', $event), 404);
        $request->validate(['response' => ['required', Rule::in(['accepted', 'tentative', 'declined'])]]);
        $series = $event->series_id === null ? $event : (CalendarEvent::query()->find($event->series_id) ?? $event);
        $participant = EventParticipant::query()->where('calendar_event_id', $event->id)->where('user_id', $user->id)->first()
            ?? EventParticipant::query()->where('calendar_event_id', $series->id)->where('user_id', $user->id)->firstOrFail();
        $respond->handle($participant, Reply::from($request->string('response')->toString()));

        return back();
    }

    public function ics(CalendarEvent $event, EventIcs $ics): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless(Gate::allows('view', $event), 404);

        return response($ics->for($event), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $event->title).'.ics"',
        ]);
    }

    /**
     * @return array{0: 'all'|'this'|'following', 1: CarbonImmutable|null}
     */
    private function scope(Request $request): array
    {
        $request->validate(['scope' => ['nullable', Rule::in(['all', 'this', 'following'])], 'occurrence' => ['nullable', 'date']]);
        $scope = match ($request->string('scope', 'all')->toString()) {
            'this' => 'this',
            'following' => 'following',
            default => 'all',
        };

        return [$scope, $request->filled('occurrence') ? CarbonImmutable::parse($request->string('occurrence')->toString())->utc() : null];
    }
}
