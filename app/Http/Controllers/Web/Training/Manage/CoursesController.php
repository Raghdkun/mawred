<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training\Manage;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Support\OrganizationOptions;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\AssignCourse;
use App\Domain\Training\Actions\AttachModuleFile;
use App\Domain\Training\Actions\ChangeCourseStatus;
use App\Domain\Training\Actions\ResetQuizAttempts;
use App\Domain\Training\Actions\SaveCourse;
use App\Domain\Training\Actions\SaveCourseModules;
use App\Domain\Training\Data\CourseEditorData;
use App\Domain\Training\Data\CourseInputData;
use App\Domain\Training\Data\CourseRefData;
use App\Domain\Training\Data\CourseRowData;
use App\Domain\Training\Data\EnrollmentData;
use App\Domain\Training\Data\ModulesInputData;
use App\Domain\Training\Enums\CourseCategory;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Queries\EnrollmentDirectoryQuery;
use App\Domain\Training\Support\LearnerClock;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Running the catalog (trainers and HR, `training.update`): the course list with enrolment
 * counts, the course editor (details, modules and quizzes, audience), publishing, and the people
 * on a course: assigning, resetting a quiz, taking someone off.
 */
final class CoursesController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('training.manage');
        $request->validate(['status' => ['nullable', Rule::enum(CourseStatus::class)], 'search' => ['nullable', 'string', 'max:100']]);
        $locale = app()->getLocale();
        $term = mb_strtolower(trim($request->string('search')->toString()));

        $courses = Course::query()
            ->withCount([
                'modules',
                'enrollments as active_count' => fn (Builder $q) => $q->where('status', EnrollmentStatus::Enrolled->value),
                'enrollments as requested_count' => fn (Builder $q) => $q->where('status', EnrollmentStatus::Requested->value),
                'enrollments as completed_count' => fn (Builder $q) => $q->where('status', EnrollmentStatus::Completed->value),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()), fn ($q) => $q->where('status', '!=', CourseStatus::Archived->value))
            ->when($term !== '', fn ($q) => $q->where(fn (\Illuminate\Contracts\Database\Query\Builder $w) => $w->whereRaw("lower(title->>'en') like ?", ["%{$term}%"])->orWhereRaw("title->>'ar' like ?", ["%{$term}%"])->orWhereRaw('lower(code) = ?', [$term])))
            ->latest('updated_at')->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (Course $course): CourseRowData => CourseRowData::fromCourse($course, $locale));

        return Inertia::render('training/manage/courses', [
            'courses' => $courses,
            'filters' => ['status' => $request->string('status')->toString(), 'search' => $request->string('search')->toString()],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('training.manage');

        return Inertia::render('training/manage/course', $this->editorProps(null));
    }

    public function store(CourseInputData $input, SaveCourse $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $course = $save->handle(Actor::user($user), $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course created. Add its modules next.')]);

        return to_route('training.manage.courses.edit', [$course, 'tab' => 'modules']);
    }

    public function edit(Course $course): Response
    {
        Gate::authorize('training.manage');

        return Inertia::render('training/manage/course', $this->editorProps($course));
    }

    public function update(CourseInputData $input, Course $course, SaveCourse $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $save->handle(Actor::user($user), $input, $course);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course saved.')]);

        return back();
    }

    public function status(Request $request, Course $course, ChangeCourseStatus $change, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $request->validate(['status' => ['required', Rule::enum(CourseStatus::class)]]);
        $to = CourseStatus::from($request->string('status')->toString());
        $change->handle(Actor::user($user), $course, $to);
        Inertia::flash('toast', ['type' => 'success', 'message' => match ($to) {
            CourseStatus::Published => __('The course is in the catalog.'),
            CourseStatus::Archived => __('The course was archived.'),
            CourseStatus::Draft => __('The course is back in draft.'),
        }]);

        return back();
    }

    public function modules(ModulesInputData $input, Course $course, SaveCourseModules $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $save->handle(Actor::user($user), $course, $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modules saved.')]);

        return back();
    }

    public function file(Request $request, Course $course, CourseModule $module, AttachModuleFile $attach, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        abort_unless($module->course_id === $course->id, 404);
        $request->validate(['file' => ['required', 'file', 'max:51200']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $attach->handle(Actor::user($user), $module, $file);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('File uploaded.')]);

        return back();
    }

    public function people(Request $request, Course $course, EnrollmentDirectoryQuery $query, LearnerClock $clock, #[CurrentUser] User $user): Response
    {
        Gate::authorize('training.manage');
        $request->validate(['status' => ['nullable', 'in:requested,enrolled,completed,rejected,cancelled,overdue'], 'search' => ['nullable', 'string', 'max:100']]);
        $status = $request->string('status')->toString();
        $today = $clock->today(null);
        $locale = app()->getLocale();

        $rows = $query->course($course->id)
            ->status($status !== '' && $status !== 'overdue' ? EnrollmentStatus::from($status) : null)
            ->overdue($status === 'overdue' ? $today : null)
            ->search($request->string('search')->toString())
            ->visibleTo($user)
            ->with(['course', 'employee'])
            ->paginate(25)->withQueryString()
            ->through(fn (Enrollment $e): EnrollmentData => EnrollmentData::fromEnrollment($e, $locale, $today, true));

        return Inertia::render('training/manage/people', [
            'course' => CourseRefData::fromCourse($course, $locale),
            'quizzes' => array_values($course->modules()->where('kind', 'quiz')->get()->map(fn (CourseModule $m): array => ['id' => $m->ulid, 'title' => FieldSpec::text($m->getTranslations('title'), $locale)])->all()),
            'enrollments' => $rows,
            'filters' => ['status' => $status, 'search' => $request->string('search')->toString()],
        ]);
    }

    public function assign(Request $request, Course $course, AssignCourse $assign, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $request->validate([
            'employees' => ['required', 'array', 'min:1', 'max:200'],
            'employees.*' => ['string', 'size:26', 'distinct', 'exists:employees,ulid'],
            'dueOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        /** @var list<string> $ulids */
        $ulids = $request->array('employees');
        $employees = Employee::query()->whereIn('ulid', $ulids)->get()->filter(fn (Employee $e): bool => Gate::allows('training.assign', $e));
        abort_if($employees->isEmpty(), 403);

        $assign->handle(Actor::user($user), $course, $employees, $request->filled('dueOn') ? $request->string('dueOn')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count person enrolled.|:count people enrolled.', $employees->count(), ['count' => $employees->count()])]);

        return back();
    }

    public function resetQuiz(Enrollment $enrollment, CourseModule $module, ResetQuizAttempts $reset, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('training.manageEnrollment', $enrollment) && $module->course_id === $enrollment->course_id, 404);
        $reset->handle(Actor::user($user), $enrollment, $module);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The learner can take the quiz again.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function editorProps(?Course $course): array
    {
        return [
            'course' => $course instanceof Course ? CourseEditorData::fromCourse($course, app()->getLocale()) : null,
            'categories' => array_map(fn (CourseCategory $c): string => $c->value, CourseCategory::cases()),
            'employmentTypes' => OrganizationOptions::entries(CatalogKind::EmploymentType),
        ];
    }
}
