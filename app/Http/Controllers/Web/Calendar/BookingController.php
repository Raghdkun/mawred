<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Calendar;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Actions\BookSlot;
use App\Domain\Calendar\Actions\RetireBookingPage;
use App\Domain\Calendar\Actions\SaveBookingPage;
use App\Domain\Calendar\Data\BookingPageInputData;
use App\Domain\Calendar\Models\BookingPage;
use App\Domain\Calendar\Support\BookingSlots;
use App\Domain\Calendar\Support\ViewerZone;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Booking (D77): owners manage their booking pages (`calendar.update`); everyone in a page's
 * audience sees its open slots for the next four weeks and books one.
 */
final class BookingController extends Controller
{
    public function pages(#[CurrentUser] User $user): Response
    {
        Gate::authorize('calendar.update');

        return Inertia::render('calendar/booking-pages', [
            'pages' => array_values(BookingPage::query()->where('owner_user_id', $user->id)->latest()->get()->map(fn (BookingPage $p): array => $this->page($p, withSetup: true))->all()),
            'timezone' => ViewerZone::of($user),
        ]);
    }

    public function store(#[CurrentUser] User $user, BookingPageInputData $input, SaveBookingPage $save): RedirectResponse
    {
        Gate::authorize('calendar.update');
        $save->handle(Actor::user($user), $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking page saved.')]);

        return back();
    }

    public function update(#[CurrentUser] User $user, BookingPage $page, BookingPageInputData $input, SaveBookingPage $save): RedirectResponse
    {
        abort_unless($page->owner_user_id === $user->id, 404);
        $save->handle(Actor::user($user), $input, $page);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking page saved.')]);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, BookingPage $page, RetireBookingPage $retire): RedirectResponse
    {
        abort_unless($page->owner_user_id === $user->id, 404);
        $retire->handle($page);

        return back();
    }

    public function index(#[CurrentUser] User $user): Response
    {
        $pages = BookingPage::query()->with('owner')->where('is_active', true)->orderBy('id')->get()
            ->filter(fn (BookingPage $p): bool => BookingSlots::canBook($p, $user));

        return Inertia::render('calendar/book', [
            'pages' => array_values($pages->map(fn (BookingPage $p): array => $this->page($p))->all()),
            'page' => null,
            'slots' => [],
            'timezone' => ViewerZone::of($user),
        ]);
    }

    public function show(#[CurrentUser] User $user, BookingPage $page): Response
    {
        abort_unless(BookingSlots::canBook($page, $user), 404);
        $now = CarbonImmutable::now('UTC');
        $slots = BookingSlots::between($page, $now, $now->addWeeks(4), $now);

        return Inertia::render('calendar/book', [
            'pages' => [],
            'page' => $this->page($page->load('owner')),
            'slots' => array_map(fn (array $s): array => ['start' => CarbonImmutable::createFromTimestampUTC($s[0])->toIso8601String(), 'end' => CarbonImmutable::createFromTimestampUTC($s[1])->toIso8601String()], array_slice($slots, 0, 200)),
            'timezone' => ViewerZone::of($user),
        ]);
    }

    public function book(Request $request, #[CurrentUser] User $user, BookingPage $page, BookSlot $book): RedirectResponse
    {
        abort_unless(BookingSlots::canBook($page, $user), 404);
        $request->validate(['start' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:500']]);
        $event = $book->handle(Actor::user($user), $page, CarbonImmutable::parse($request->string('start')->toString())->utc(), $request->filled('note') ? $request->string('note')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booked. It’s on your calendar.')]);

        return to_route('calendar.show', $event);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(BookingPage $page, bool $withSetup = false): array
    {
        return [
            'id' => $page->ulid,
            'title' => $page->getTranslations('title'),
            'description' => $page->getTranslations('description'),
            'location' => $page->location,
            'durationMinutes' => $page->duration_minutes,
            'owner' => $page->owner->name ?? '',
            'active' => $page->is_active,
            ...($withSetup ? [
                'bufferMinutes' => $page->buffer_minutes,
                'windows' => $page->windows,
                'timezone' => $page->timezone,
                'startsOn' => $page->starts_on->toDateString(),
                'endsOn' => $page->ends_on?->toDateString(),
                'audience' => $page->audience,
                'branchId' => $page->branch_id === null ? null : Branch::withTrashed()->whereKey($page->branch_id)->value('ulid'),
                'departmentId' => $page->department_id === null ? null : Department::withTrashed()->whereKey($page->department_id)->value('ulid'),
            ] : []),
        ];
    }
}
