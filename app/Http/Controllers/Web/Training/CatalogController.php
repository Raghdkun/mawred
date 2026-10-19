<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\CancelEnrollment;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Actions\JoinLearningPath;
use App\Domain\Training\Data\CourseCardData;
use App\Domain\Training\Data\LearningPathData;
use App\Domain\Training\Enums\CourseCategory;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\LearningPath;
use App\Domain\Training\Support\CatalogCourses;
use App\Domain\Training\Support\CoursePage;
use App\Domain\Training\Support\LearnerClock;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The training catalog (handoff 33): published courses with search and category pills, learning
 * paths, a course's page and a path's stepper; enrolling (or asking to, D99), joining a path and
 * leaving a course the learner chose.
 */
final class CatalogController extends Controller
{
    public function index(Request $request, CatalogCourses $catalog, LearnerClock $clock, #[CurrentUser] User $user): Response
    {
        Gate::authorize('training.browse');
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(CourseCategory::class)],
            'view' => ['nullable', 'in:courses,paths'],
        ]);
        $locale = app()->getLocale();
        $employee = CoursePage::employeeOf($user);
        $today = $clock->today($employee);
        $view = $request->string('view', 'courses')->toString();
        $mine = $catalog->latestEnrollments($employee);

        $courses = $catalog->published($request->string('search')->toString(), $request->filled('category') ? CourseCategory::from($request->string('category')->toString()) : null)
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Course $course): CourseCardData => CourseCardData::fromCourse($course, $mine[$course->id] ?? null, $locale, $today));

        return Inertia::render('training/catalog', [
            'courses' => $courses,
            'paths' => $view === 'paths' ? array_values(LearningPath::query()->where('status', CourseStatus::Published->value)->with('courses')->orderBy('id')->get()
                ->map(fn (LearningPath $path): LearningPathData => LearningPathData::fromPath($path, $mine, $locale))->all()) : [],
            'filters' => ['search' => $request->string('search')->toString(), 'category' => $request->string('category')->toString(), 'view' => $view],
            'categories' => array_map(fn (CourseCategory $c): string => $c->value, CourseCategory::cases()),
            'can' => ['manage' => Gate::allows('training.manage')],
        ]);
    }

    public function show(Course $course, CoursePage $page, #[CurrentUser] User $user): Response
    {
        Gate::authorize('training.browse');
        abort_unless($course->status === CourseStatus::Published || Gate::allows('training.manage'), 404);

        return Inertia::render('training/course', $page->for($course, $user, app()->getLocale()));
    }

    public function path(LearningPath $path, CatalogCourses $catalog, #[CurrentUser] User $user): Response
    {
        Gate::authorize('training.browse');
        abort_unless($path->status === CourseStatus::Published || Gate::allows('training.manage'), 404);
        $path->load('courses');

        return Inertia::render('training/path', [
            'path' => LearningPathData::fromPath($path, $catalog->latestEnrollments(CoursePage::employeeOf($user)), app()->getLocale()),
            'can' => ['join' => $path->status === CourseStatus::Published && Gate::allows('training.enrol')],
        ]);
    }

    public function enrol(Course $course, Enrol $enrol, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.enrol');
        $employee = CoursePage::employeeOf($user);
        abort_unless($employee instanceof Employee && $course->status === CourseStatus::Published, 404);

        $enrollment = $enrol->handle(Actor::user($user), $employee, $course, EnrollmentSource::SelfService);
        Inertia::flash('toast', ['type' => 'success', 'message' => $enrollment->status === EnrollmentStatus::Requested
            ? __('Your manager has been asked to approve your enrolment.')
            : __('You’re enrolled. Start whenever you like.')]);

        return back();
    }

    public function join(LearningPath $path, JoinLearningPath $join, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.enrol');
        $employee = CoursePage::employeeOf($user);
        abort_unless($employee instanceof Employee, 404);

        $join->handle(Actor::user($user), $employee, $path);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('You joined the learning path.')]);

        return back();
    }

    public function leave(Enrollment $enrollment, CancelEnrollment $cancel, #[CurrentUser] User $user): RedirectResponse
    {
        $own = $enrollment->employee()->value('user_id') === $user->id && in_array($enrollment->source, [EnrollmentSource::SelfService, EnrollmentSource::Path], true);
        abort_unless($own || Gate::allows('training.manageEnrollment', $enrollment), 404);

        $cancel->handle(Actor::user($user), $enrollment, $own ? FieldSpec::string(__('Left by the learner.')) : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The enrolment was cancelled.')]);

        return back();
    }
}
