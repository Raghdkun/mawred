<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Data\EnrollmentData;
use App\Domain\Training\Data\LearningPathData;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\LearningPath;
use App\Domain\Training\Support\CatalogCourses;
use App\Domain\Training\Support\CoursePage;
use App\Domain\Training\Support\LearnerClock;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My learning (handoff 34): what the viewer is on (with due dates and overdue flags), waiting
 * for approval, finished, and the learning paths they joined.
 */
final class MyLearningController extends Controller
{
    public function __invoke(CatalogCourses $catalog, LearnerClock $clock, #[CurrentUser] User $user): Response
    {
        Gate::authorize('training.browse');
        $employee = CoursePage::employeeOf($user);
        $locale = app()->getLocale();
        $today = $clock->today($employee);

        $enrollments = $employee instanceof Employee
            ? array_values(Enrollment::query()->with('course')->where('employee_id', $employee->id)
                ->whereIn('status', [EnrollmentStatus::Requested->value, EnrollmentStatus::Enrolled->value, EnrollmentStatus::Completed->value])
                ->orderByRaw("case status when 'enrolled' then 0 when 'requested' then 1 else 2 end")
                ->orderByRaw('due_on asc nulls last')->latest('completed_at')->limit(200)->get()->all())
            : [];
        $rows = array_map(fn (Enrollment $e): EnrollmentData => EnrollmentData::fromEnrollment($e, $locale, $today), $enrollments);
        $pathIds = array_values(array_unique(array_filter(array_map(fn (Enrollment $e): ?int => $e->learning_path_id, $enrollments))));
        $mine = $catalog->latestEnrollments($employee);

        return Inertia::render('training/my', [
            'enrollments' => $rows,
            'stats' => [
                'active' => count(array_filter($rows, fn (EnrollmentData $e): bool => $e->status === 'enrolled')),
                'overdue' => count(array_filter($rows, fn (EnrollmentData $e): bool => $e->overdue)),
                'completed' => count(array_filter($rows, fn (EnrollmentData $e): bool => $e->status === 'completed')),
            ],
            'paths' => array_values(LearningPath::query()->whereIn('id', $pathIds)->with('courses')->get()
                ->map(fn (LearningPath $path): LearningPathData => LearningPathData::fromPath($path, $mine, $locale))->all()),
            'hasEmployee' => $employee instanceof Employee,
        ]);
    }
}
