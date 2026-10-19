<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training;

use App\Domain\Access\Models\User;
use App\Domain\Training\Actions\CompleteModule;
use App\Domain\Training\Actions\SubmitQuizAttempt;
use App\Domain\Training\Data\CourseRefData;
use App\Domain\Training\Data\EnrollmentData;
use App\Domain\Training\Data\ModuleData;
use App\Domain\Training\Data\ModuleSummaryData;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ModuleProgress;
use App\Domain\Training\Models\QuizAttempt;
use App\Domain\Training\Support\CoursePage;
use App\Domain\Training\Support\LearnerClock;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Working through a course (handoff 34): a module with the outline beside it, marking it done,
 * quizzes with a result screen (D102), and a file module's download. Trainers can preview.
 */
final class LearnController extends Controller
{
    public function show(Course $course, CourseModule $module, LearnerClock $clock, #[CurrentUser] User $user): Response
    {
        abort_unless($module->course_id === $course->id, 404);
        $enrollment = $this->viewing($course, $user);
        $locale = app()->getLocale();
        $done = $enrollment instanceof Enrollment ? ModuleProgress::query()->where('enrollment_id', $enrollment->id)->pluck('course_module_id')->all() : [];

        /** @var array<int, list<QuizAttempt>> $attempts */
        $attempts = [];

        if ($enrollment instanceof Enrollment) {
            foreach (QuizAttempt::query()->where('enrollment_id', $enrollment->id)->oldest('submitted_at')->orderBy('id')->get() as $attempt) {
                $attempts[$attempt->course_module_id][] = $attempt;
            }
        }

        $modules = array_values($course->modules()->get()->all());
        $index = array_search($module->id, array_map(fn (CourseModule $m): int => $m->id, $modules), true);
        $module->load('questions');
        $outline = [];

        foreach ($modules as $item) {
            $outline[] = ModuleSummaryData::fromModule($item, $locale, in_array($item->id, $done, true), array_map(
                fn (QuizAttempt $a): array => ['score' => $a->score, 'passed' => $a->passed],
                $attempts[$item->id] ?? [],
            ));
        }

        $previous = is_int($index) ? ($modules[$index - 1] ?? null) : null;
        $next = is_int($index) ? ($modules[$index + 1] ?? null) : null;

        return Inertia::render('training/learn', [
            'course' => CourseRefData::fromCourse($course, $locale),
            'outline' => $outline,
            'module' => ModuleData::fromModule($module, $locale, in_array($module->id, $done, true), $attempts[$module->id] ?? []),
            'previous' => $previous?->ulid,
            'next' => $next?->ulid,
            'enrollment' => $enrollment instanceof Enrollment ? EnrollmentData::fromEnrollment($enrollment, $locale, $clock->today(CoursePage::employeeOf($user))) : null,
            'preview' => ! $enrollment instanceof Enrollment,
        ]);
    }

    public function complete(Course $course, CourseModule $module, CompleteModule $complete, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($module->course_id === $course->id, 404);
        $enrollment = $complete->handle($this->learning($course, $user), $module);

        if ($enrollment->status === EnrollmentStatus::Completed) {
            Inertia::flash('completed', true);

            return to_route('training.courses.show', $course);
        }

        $next = $course->modules()->where('position', '>', $module->position)->first();

        return $next instanceof CourseModule ? to_route('training.learn', [$course, $next]) : to_route('training.courses.show', $course);
    }

    public function attempt(Request $request, Course $course, CourseModule $module, SubmitQuizAttempt $submit, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($module->course_id === $course->id && $module->kind === ModuleKind::Quiz, 404);
        $request->validate([
            'answers' => ['present', 'array', 'max:50'],
            'answers.*' => ['array', 'max:8'],
            'answers.*.*' => ['string', 'max:26'],
        ]);
        $enrollment = $this->learning($course, $user);

        /** @var array<string, list<string>> $answers */
        $answers = $request->array('answers');
        $attempt = $submit->handle($enrollment, $module, $answers);

        if ($attempt->passed && $enrollment->fresh()?->status === EnrollmentStatus::Completed) {
            Inertia::flash('completed', true);
        }

        return back();
    }

    public function file(Course $course, CourseModule $module, #[CurrentUser] User $user): StreamedResponse
    {
        abort_unless($module->course_id === $course->id && $module->kind === ModuleKind::File && $module->file_path !== null, 404);
        $this->viewing($course, $user);

        return Storage::disk(CourseModule::DISK)->download($module->file_path, $module->file_name ?? basename($module->file_path));
    }

    /** The viewer's enrolment on the course, or null for a trainer previewing it. */
    private function viewing(Course $course, User $user): ?Enrollment
    {
        $enrollment = CoursePage::latest(CoursePage::employeeOf($user), $course);

        if ($enrollment instanceof Enrollment && in_array($enrollment->status, [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed], true)) {
            return $enrollment;
        }

        abort_unless(Gate::allows('training.manage'), 404);

        return null;
    }

    /** The viewer's enrolment, which they need to make progress. */
    private function learning(Course $course, User $user): Enrollment
    {
        $enrollment = CoursePage::latest(CoursePage::employeeOf($user), $course);
        abort_unless($enrollment instanceof Enrollment && in_array($enrollment->status, [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed], true), 403);

        return $enrollment;
    }
}
