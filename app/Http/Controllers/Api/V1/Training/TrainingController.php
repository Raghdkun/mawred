<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Training;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\BookSession;
use App\Domain\Training\Actions\CancelBooking;
use App\Domain\Training\Actions\CancelEnrollment;
use App\Domain\Training\Actions\CheckIn;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Data\CertificateData;
use App\Domain\Training\Data\CourseRefData;
use App\Domain\Training\Data\EnrollmentData;
use App\Domain\Training\Data\ExternalTrainingData;
use App\Domain\Training\Data\SessionData;
use App\Domain\Training\Enums\CourseCategory;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\SessionStatus;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ExternalTraining;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\CatalogCourses;
use App\Domain\Training\Support\CoursePage;
use App\Domain\Training\Support\LearnerClock;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Training\CourseResource;
use App\Http\Resources\Api\V1\Training\EnrollmentResource;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Training enrolment over /api/v1 (ADR-0012, D27): the catalog, the viewer's own enrolments,
 * enrolling (or asking to, D99) and leaving a course they chose, and an employee's training
 * record for the profile tab. Out-of-scope people answer 404.
 */
final class TrainingController extends Controller
{
    public function courses(Request $request, CatalogCourses $catalog): AnonymousResourceCollection
    {
        Gate::authorize('training.browse');
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(CourseCategory::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $catalog->published($request->string('search')->toString(), $request->filled('category') ? CourseCategory::from($request->string('category')->toString()) : null)
            ->cursorPaginate($request->integer('per_page', 25))->withQueryString();

        return CourseResource::collection($page);
    }

    public function course(Course $course): CourseResource
    {
        Gate::authorize('training.browse');
        abort_unless($course->status === CourseStatus::Published, 404);

        return new CourseResource($course);
    }

    public function mine(Request $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        Gate::authorize('training.browse');
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $employee = CoursePage::employeeOf($user);

        $page = Enrollment::query()->with('course')->where('employee_id', $employee->id ?? 0)
            ->latest('created_at')->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 25))->withQueryString();

        return EnrollmentResource::collection($page);
    }

    public function enrol(Course $course, Enrol $enrol, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('training.enrol');
        $employee = CoursePage::employeeOf($user);
        abort_unless($employee instanceof Employee && $course->status === CourseStatus::Published, 404);

        $enrollment = $enrol->handle(Actor::token($user, Channel::Api), $employee, $course, EnrollmentSource::SelfService);

        return (new EnrollmentResource($enrollment->load('course')))->response()->setStatusCode(201);
    }

    public function leave(Enrollment $enrollment, CancelEnrollment $cancel, #[CurrentUser] User $user): EnrollmentResource
    {
        $own = $enrollment->employee()->value('user_id') === $user->id && in_array($enrollment->source, [EnrollmentSource::SelfService, EnrollmentSource::Path], true);
        abort_unless($own, 404);

        return new EnrollmentResource($cancel->handle(Actor::token($user, Channel::Api), $enrollment)->load('course'));
    }

    /** Someone's training record (the profile's Training tab). */
    public function record(Employee $employee, LearnerClock $clock): JsonResponse
    {
        abort_unless(Gate::allows('training.viewRecord', $employee), 404);
        $locale = app()->getLocale();
        $today = $clock->today($employee);

        $enrollments = Enrollment::query()->with('course')->where('employee_id', $employee->id)
            ->latest('created_at')->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => [
            'enrollments' => $enrollments->map(fn (Enrollment $e): EnrollmentData => EnrollmentData::fromEnrollment($e, $locale, $today))->values(),
            'certificates' => Certificate::query()->where('employee_id', $employee->id)->latest('issued_on')->orderByDesc('id')->limit(100)->get()
                ->map(fn (Certificate $c): CertificateData => CertificateData::fromCertificate($c, $locale, $today))->values(),
            'external' => ExternalTraining::query()->where('employee_id', $employee->id)->latest('starts_on')->orderByDesc('id')->limit(100)->get()
                ->map(fn (ExternalTraining $r): ExternalTrainingData => ExternalTrainingData::fromRecord($r, $locale))->values(),
        ], 'meta' => [
            'canManage' => Gate::allows('training.assign', $employee),
            'courses' => Gate::allows('training.assign', $employee)
                ? Course::query()->where('status', CourseStatus::Published->value)->orderBy('code')->limit(300)->get()
                    ->map(fn (Course $c): CourseRefData => CourseRefData::fromCourse($c, $locale))->values()
                : [],
        ]]);
    }

    /** Sessions of a course for the viewer, with their own seat (D107). */
    public function sessions(Course $course, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('training.browse');
        abort_unless($course->status === CourseStatus::Published, 404);
        $enrollment = CoursePage::latest(CoursePage::employeeOf($user), $course);
        $mine = [];

        foreach ($enrollment instanceof Enrollment ? SessionBooking::query()->where('enrollment_id', $enrollment->id)->get() : [] as $booking) {
            $mine[$booking->training_session_id] = $booking;
        }

        return response()->json(['data' => TrainingSession::query()->with('trainer')->where('course_id', $course->id)->where('status', SessionStatus::Scheduled->value)
            ->where('ends_at', '>=', now())->oldest('starts_at')->limit(50)->get()
            ->map(fn (TrainingSession $s): SessionData => SessionData::fromSession($s, $mine[$s->id] ?? null))->values()]);
    }

    public function book(Course $course, TrainingSession $session, BookSession $book, #[CurrentUser] User $user): JsonResponse
    {
        abort_unless($session->course_id === $course->id, 404);
        $enrollment = CoursePage::latest(CoursePage::employeeOf($user), $course);
        abort_unless($enrollment instanceof Enrollment, 404);
        $booking = $book->handle(Actor::token($user, Channel::Api), $enrollment, $session);

        return response()->json(['data' => SessionData::fromSession($session->fresh() ?? $session, $booking)], 201);
    }

    public function cancelBooking(SessionBooking $booking, CancelBooking $cancel, #[CurrentUser] User $user): JsonResponse
    {
        abort_unless($booking->enrollment()->firstOrFail()->employee()->value('user_id') === $user->id, 404);
        $cancel->handle(Actor::token($user, Channel::Api), $booking);

        return response()->json(['data' => ['id' => $booking->ulid, 'status' => $booking->status->value]]);
    }

    /** Check in with the code from the trainer's QR (D101), e.g. scanned in the mobile app. */
    public function checkIn(Request $request, CheckIn $checkIn, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:300']]);
        $code = $request->string('code')->toString();
        $query = parse_url($code, PHP_URL_QUERY);

        if (is_string($query)) {
            parse_str($query, $params);
            $code = is_string($params['code'] ?? null) ? $params['code'] : $code;
        }

        $booking = $checkIn->handle($user, $code, CarbonImmutable::now());

        return response()->json(['data' => ['id' => $booking->ulid, 'attendedAt' => $booking->attended_at?->toIso8601String()]]);
    }
}
