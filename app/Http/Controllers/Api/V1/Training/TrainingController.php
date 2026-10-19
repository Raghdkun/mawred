<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Training;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\CancelEnrollment;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Data\EnrollmentData;
use App\Domain\Training\Enums\CourseCategory;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Support\CatalogCourses;
use App\Domain\Training\Support\CoursePage;
use App\Domain\Training\Support\LearnerClock;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Training\CourseResource;
use App\Http\Resources\Api\V1\Training\EnrollmentResource;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
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
        ]]);
    }
}
