<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training\Manage;

use App\Domain\Access\Models\User;
use App\Domain\Training\Actions\CancelSession;
use App\Domain\Training\Actions\MarkAttendance;
use App\Domain\Training\Actions\SaveSession;
use App\Domain\Training\Data\CourseRefData;
use App\Domain\Training\Data\RosterRowData;
use App\Domain\Training\Data\SessionData;
use App\Domain\Training\Data\SessionInputData;
use App\Domain\Training\Enums\SessionStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\CheckInCodes;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Qr\QrSvg;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sessions for trainers (D98, D101, D107): planning and cancelling them, the roster with
 * attendance, and the full-screen rotating QR people scan to check in.
 */
final class SessionsController extends Controller
{
    public function store(SessionInputData $input, Course $course, SaveSession $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $save->handle(Actor::user($user), $course, $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session saved. It’s on the trainer’s calendar.')]);

        return back();
    }

    public function update(SessionInputData $input, TrainingSession $session, SaveSession $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $save->handle(Actor::user($user), $session->course()->firstOrFail(), $input, $session);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session saved. It’s on the trainer’s calendar.')]);

        return back();
    }

    public function cancel(TrainingSession $session, CancelSession $cancel, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $cancel->handle(Actor::user($user), $session);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The session was cancelled and everyone booked was told.')]);

        return back();
    }

    public function show(TrainingSession $session): Response
    {
        Gate::authorize('training.manage');
        $locale = app()->getLocale();
        $session->load('trainer');

        return Inertia::render('training/manage/session', [
            'course' => CourseRefData::fromCourse($session->course()->firstOrFail(), $locale),
            'session' => SessionData::fromSession($session, null, true),
            'roster' => array_values($session->bookings()->with('enrollment.employee')->orderByRaw("case status when 'booked' then 0 when 'waitlisted' then 1 else 2 end")->oldest('booked_at')->oldest('waitlisted_at')->get()
                ->map(fn (SessionBooking $b): RosterRowData => RosterRowData::fromBooking($b, $locale))->all()),
        ]);
    }

    public function attendance(Request $request, SessionBooking $booking, MarkAttendance $mark, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $request->validate(['attended' => ['required', 'boolean']]);
        $mark->handle(Actor::user($user), $booking, $request->boolean('attended'));

        return back();
    }

    /** The full-screen check-in QR (D101). */
    public function display(TrainingSession $session): Response
    {
        Gate::authorize('training.manage');
        abort_unless($session->status === SessionStatus::Scheduled, 404);

        return Inertia::render('training/manage/check-in-display', [
            'course' => CourseRefData::fromCourse($session->course()->firstOrFail(), app()->getLocale()),
            'session' => SessionData::fromSession($session, null, true),
        ]);
    }

    /** The current code, polled by the display; it rotates every 30 seconds. */
    public function code(TrainingSession $session): JsonResponse
    {
        Gate::authorize('training.manage');
        abort_unless($session->status === SessionStatus::Scheduled, 404);
        $now = now()->getTimestamp();
        $url = CheckInCodes::url(CheckInCodes::code($session, $now));

        return response()->json([
            'url' => $url,
            'svg' => QrSvg::render($url),
            'refreshIn' => CheckInCodes::SLOT_SECONDS - ($now % CheckInCodes::SLOT_SECONDS),
            'attended' => $session->bookings()->whereNotNull('attended_at')->count(),
        ])->header('Cache-Control', 'no-store');
    }
}
