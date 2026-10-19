<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training\Manage;

use App\Domain\Access\Models\User;
use App\Domain\Training\Actions\ChangeLearningPathStatus;
use App\Domain\Training\Actions\SaveLearningPath;
use App\Domain\Training\Data\CourseRefData;
use App\Domain\Training\Data\LearningPathEditorData;
use App\Domain\Training\Data\LearningPathInputData;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\LearningPath;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Learning paths for trainers and HR: the list, the editor (courses in order), publishing. */
final class PathsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('training.manage');
        $locale = app()->getLocale();

        return Inertia::render('training/manage/paths', [
            'paths' => array_values(LearningPath::query()->with('courses')->latest('updated_at')->orderByDesc('id')->limit(200)->get()
                ->map(fn (LearningPath $path): LearningPathEditorData => LearningPathEditorData::fromPath($path))->all()),
            'courses' => array_values(Course::query()->whereIn('status', [CourseStatus::Draft->value, CourseStatus::Published->value])->orderBy('code')->limit(500)->get()
                ->map(fn (Course $c): CourseRefData => CourseRefData::fromCourse($c, $locale))->all()),
        ]);
    }

    public function store(LearningPathInputData $input, SaveLearningPath $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $save->handle(Actor::user($user), $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Learning path saved.')]);

        return back();
    }

    public function update(LearningPathInputData $input, LearningPath $path, SaveLearningPath $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $save->handle(Actor::user($user), $input, $path);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Learning path saved.')]);

        return back();
    }

    public function status(Request $request, LearningPath $path, ChangeLearningPathStatus $change, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('training.manage');
        $request->validate(['status' => ['required', Rule::enum(CourseStatus::class)]]);
        $change->handle(Actor::user($user), $path, CourseStatus::from($request->string('status')->toString()));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Learning path updated.')]);

        return back();
    }
}
