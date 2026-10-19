<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Actions\BookSession;
use App\Domain\Training\Actions\CancelBooking;
use App\Domain\Training\Actions\CheckIn;
use App\Domain\Training\Actions\SubmitFeedback;
use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\CheckInCodes;
use App\Domain\Training\Support\CoursePage;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The learner's side of sessions (D101, D107, D109): booking a seat or joining the waitlist,
 * giving it up, checking in from the trainer's QR, and rating a finished course.
 */
final class SessionsController extends Controller
{
    public function book(Course $course, TrainingSession $session, BookSession $book, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($session->course_id === $course->id, 404);
        $enrollment = CoursePage::latest(CoursePage::employeeOf($user), $course);
        abort_unless($enrollment instanceof Enrollment, 403);

        $booking = $book->handle(Actor::user($user), $enrollment, $session);
        Inertia::flash('toast', ['type' => 'success', 'message' => $booking->status === BookingStatus::Booked
            ? __('Your seat is booked. It’s in your calendar.')
            : __('The session is full. You’re on the waitlist and will get the first free seat.')]);

        return back();
    }

    public function cancel(SessionBooking $booking, CancelBooking $cancel, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($booking->enrollment()->firstOrFail()->employee()->value('user_id') === $user->id, 404);
        $cancel->handle(Actor::user($user), $booking);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your seat was released.')]);

        return back();
    }

    public function checkIn(Request $request, #[CurrentUser] User $user): Response
    {
        $request->validate(['code' => ['required', 'string', 'max:120']]);
        $code = $request->string('code')->toString();
        $session = CheckInCodes::session($code, CarbonImmutable::now()->getTimestamp());
        $course = $session?->course()->first();

        return Inertia::render('training/check-in', [
            'code' => $code,
            'session' => $session instanceof TrainingSession && $course instanceof Course ? [
                'course' => FieldSpec::text($course->getTranslations('title'), app()->getLocale()),
                'startsAt' => $session->starts_at->toIso8601String(),
                'timezone' => $session->timezone,
                'location' => $session->location,
            ] : null,
        ]);
    }

    public function confirmCheckIn(Request $request, CheckIn $checkIn, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:120']]);
        $booking = $checkIn->handle($user, $request->string('code')->toString(), CarbonImmutable::now());
        Inertia::flash('checkedIn', true);

        return to_route('training.courses.show', $booking->session()->firstOrFail()->course()->firstOrFail());
    }

    public function feedback(Request $request, Enrollment $enrollment, SubmitFeedback $submit, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($enrollment->employee()->value('user_id') === $user->id, 404);
        $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $submit->handle($enrollment, $request->integer('rating'), $request->filled('comment') ? $request->string('comment')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks for your feedback.')]);

        return back();
    }
}
