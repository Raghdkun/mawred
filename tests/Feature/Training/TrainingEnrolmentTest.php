<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Actions\ChangePlacement;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\ChangeCourseStatus;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Actions\SaveCourse;
use App\Domain\Training\Actions\SaveCourseModules;
use App\Domain\Training\Actions\SeedTrainingDefaults;
use App\Domain\Training\Data\CourseInputData;
use App\Domain\Training\Data\ModulesInputData;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Events\EnrollmentCompleted;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\LearningPath;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 7a: the catalog, enrolment with the manager's approval (D99), mandatory courses for an
| audience (D100), modules and quizzes (D98, D102), learning paths, and who sees what.
*/
beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    resolve(SeedTrainingDefaults::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->ops = Department::factory()->create(['code' => 'OPS']);
    $this->trainer = userWithRoles('trainer');
    $this->managerUser = userWithRoles('branch_manager');
    $this->manager = Employee::factory()->placedIn($this->branch, $this->ops)->create(['user_id' => $this->managerUser->id]);
    $this->learnerUser = userWithRoles('employee');
    $this->learner = Employee::factory()->placedIn($this->branch, $this->ops, $this->manager)->create(['user_id' => $this->learnerUser->id]);
});

/**
 * A published course: a text lesson and a two-question quiz (pass mark 70, two attempts).
 */
function trainingCourse(object $test, array $details = []): Course
{
    $course = resolve(SaveCourse::class)->handle(Actor::user($test->trainer), CourseInputData::from([
        'title' => ['en' => 'Food safety', 'ar' => 'سلامة الغذاء'], 'category' => 'safety', ...$details,
    ]));
    resolve(SaveCourseModules::class)->handle(Actor::user($test->trainer), $course, ModulesInputData::from(['modules' => [
        ['kind' => 'text', 'title' => ['en' => 'Why it matters'], 'body' => ['en' => 'Clean hands save lives.']],
        ['kind' => 'quiz', 'title' => ['en' => 'Check'], 'passMark' => 70, 'maxAttempts' => 2, 'questions' => [
            ['type' => 'single', 'prompt' => ['en' => 'Wash hands for?'], 'options' => [
                ['id' => 'a', 'label' => ['en' => '5 seconds'], 'correct' => false],
                ['id' => 'b', 'label' => ['en' => '20 seconds'], 'correct' => true],
            ]],
            ['type' => 'true_false', 'prompt' => ['en' => 'Raw chicken goes on the top shelf.'], 'options' => [
                ['id' => 't', 'label' => ['en' => 'True'], 'correct' => false],
                ['id' => 'f', 'label' => ['en' => 'False'], 'correct' => true],
            ]],
        ]],
    ]]));

    return resolve(ChangeCourseStatus::class)->handle(Actor::user($test->trainer), $course->fresh() ?? $course, CourseStatus::Published);
}

function trainingQuestions(Course $course): array
{
    return $course->modules()->where('kind', 'quiz')->sole()->questions()->pluck('ulid')->all();
}

it('publishes only complete courses, and hides drafts from the catalog', function (): void {
    $draft = resolve(SaveCourse::class)->handle(Actor::user($this->trainer), CourseInputData::from(['title' => ['en' => 'Draft course']]));
    expect(fn () => resolve(ChangeCourseStatus::class)->handle(Actor::user($this->trainer), $draft, CourseStatus::Published))->toThrow(DomainRuleViolation::class)
        ->and($draft->code)->toStartWith('TRN-');

    $course = trainingCourse($this);

    $this->actingAs($this->learnerUser)->get(route('training.catalog'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('training/catalog')->has('courses.data', 1)->where('courses.data.0.title', 'Food safety')->where('courses.data.0.modules', 2));
    $this->get(route('training.courses.show', $draft))->assertNotFound();
    $this->get(route('training.manage.courses.index'))->assertForbidden();
    $this->actingAs($this->trainer)->get(route('training.courses.show', $draft))->assertOk();
    $this->get(route('training.manage.courses.edit', $course))
        ->assertInertia(fn (Assert $page): Assert => $page->component('training/manage/course')->where('course.modules.1.questions.0.options.1.correct', true));
});

it('enrols straight away, or asks the manager when the course needs approval', function (): void {
    $open = trainingCourse($this);
    $this->actingAs($this->learnerUser)->post(route('training.courses.enrol', $open))->assertRedirect();
    expect(Enrollment::query()->where('course_id', $open->id)->sole())->status->toBe(EnrollmentStatus::Enrolled)->source->toBe(EnrollmentSource::SelfService);

    $gated = trainingCourse($this, ['requiresApproval' => true]);
    $this->post(route('training.courses.enrol', $gated))->assertRedirect();
    $enrollment = Enrollment::query()->where('course_id', $gated->id)->sole();
    expect($enrollment->status)->toBe(EnrollmentStatus::Requested);

    // The manager sees what it's about in the inbox and approves.
    $task = WorkflowTask::query()->where('workflow_instance_id', $enrollment->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
    expect($task->assignee_user_id)->toBe($this->managerUser->id);
    resolve(WorkflowEngine::class)->decide($task, Decision::Approved, $this->managerUser);
    expect($enrollment->fresh()?->status)->toBe(EnrollmentStatus::Enrolled);

    // A second gated course, turned down.
    $other = trainingCourse($this, ['requiresApproval' => true]);
    $second = resolve(Enrol::class)->handle(Actor::user($this->learnerUser), $this->learner, $other, EnrollmentSource::SelfService);
    $task = WorkflowTask::query()->where('workflow_instance_id', $second->workflow_instance_id)->sole();
    resolve(WorkflowEngine::class)->decide($task, Decision::Rejected, $this->managerUser, 'Not this quarter');
    expect($second->fresh()?->status)->toBe(EnrollmentStatus::Rejected);

    // Leaving a course the learner chose.
    $this->delete(route('training.enrollments.cancel', $enrollment))->assertRedirect();
    expect($enrollment->fresh()?->status)->toBe(EnrollmentStatus::Cancelled);
});

it('scores quizzes with limited attempts and completes the course', function (): void {
    Event::fake([EnrollmentCompleted::class]);
    $course = trainingCourse($this);
    [$text, $quiz] = $course->modules()->get()->all();
    [$q1, $q2] = trainingQuestions($course);
    $this->actingAs($this->learnerUser)->post(route('training.courses.enrol', $course));
    $enrollment = Enrollment::query()->sole();

    $this->post(route('training.learn.complete', [$course, $text]))->assertRedirect(route('training.learn', [$course, $quiz]));
    expect($enrollment->fresh()?->progress)->toBe(50);

    $this->post(route('training.learn.attempt', [$course, $quiz]), ['answers' => [$q1 => ['b'], $q2 => ['t']]])->assertRedirect();
    $this->get(route('training.learn', [$course, $quiz]))
        ->assertInertia(fn (Assert $page): Assert => $page->component('training/learn')->where('module.quiz.last.score', 50)->where('module.quiz.last.passed', false)
            ->where('module.quiz.attemptsUsed', 1)->missing('module.quiz.questions.0.options.0.correct'));
    $this->post(route('training.learn.attempt', [$course, $quiz]), ['answers' => [$q1 => ['a']]]);
    $this->post(route('training.learn.attempt', [$course, $quiz]), ['answers' => [$q1 => ['b'], $q2 => ['f']]])->assertSessionHasErrors('error');

    // The trainer gives another go; this time it passes and the course completes.
    $this->actingAs($this->trainer)->post(route('training.manage.enrollments.reset', [$enrollment, $quiz]))->assertRedirect();
    $this->actingAs($this->learnerUser)->post(route('training.learn.attempt', [$course, $quiz]), ['answers' => [$q1 => ['b'], $q2 => ['f']]])->assertRedirect();

    expect($enrollment->fresh())->status->toBe(EnrollmentStatus::Completed)->progress->toBe(100);
    Event::assertDispatched(EnrollmentCompleted::class);
    $this->post(route('training.learn.attempt', [$course, $quiz]), ['answers' => []])->assertSessionHasErrors('error');
});

it('assigns mandatory courses to their audience with due dates, including joiners and movers', function (): void {
    $elsewhere = Employee::factory()->placedIn($this->otherBranch)->create();
    $course = trainingCourse($this, ['audience' => ['branches' => [$this->branch->ulid]], 'dueDays' => 30]);

    $assigned = Enrollment::query()->where('course_id', $course->id)->get();
    expect($assigned->pluck('employee_id')->sort()->values()->all())->toBe(collect([$this->manager->id, $this->learner->id])->sort()->values()->all())
        ->and($assigned->first()?->source)->toBe(EnrollmentSource::Audience)
        ->and($assigned->first()?->due_on?->toDateString())->toBe(now('Asia/Riyadh')->addDays(30)->toDateString());

    // Someone moving into the branch picks it up; re-running assigns nobody twice.
    resolve(ChangePlacement::class)->handle(Actor::system('test'), $elsewhere, new PlacementInputData(branchId: $this->branch->ulid), now('Asia/Riyadh')->toDateString());
    expect(Enrollment::query()->where('course_id', $course->id)->where('employee_id', $elsewhere->id)->count())->toBe(1);
    $this->artisan('training:assign')->assertSuccessful();
    expect(Enrollment::query()->where('course_id', $course->id)->count())->toBe(3);

    // Assigned learners can't leave a mandatory course themselves.
    $mine = Enrollment::query()->where('course_id', $course->id)->where('employee_id', $this->learner->id)->sole();
    $this->actingAs($this->learnerUser)->delete(route('training.enrollments.cancel', $mine))->assertNotFound();
});

it('joins a learning path course by course and shows the stepper', function (): void {
    $first = trainingCourse($this);
    $second = trainingCourse($this, ['title' => ['en' => 'Allergens']]);
    $this->actingAs($this->trainer)->post(route('training.manage.paths.store'), ['title' => ['en' => 'Kitchen starter'], 'courses' => [$first->ulid, $second->ulid]])->assertRedirect();
    $path = LearningPath::query()->sole();
    $this->put(route('training.manage.paths.status', $path), ['status' => 'published'])->assertRedirect();

    $this->actingAs($this->learnerUser)->post(route('training.paths.join', $path))->assertRedirect();
    expect(Enrollment::query()->where('learning_path_id', $path->id)->count())->toBe(2);
    $this->get(route('training.paths.show', $path))
        ->assertInertia(fn (Assert $page): Assert => $page->where('path.joined', true)->where('path.courses.1.title', 'Allergens')->where('path.courses.1.status', 'enrolled'));
});

it('keeps training records to the people allowed to see them', function (): void {
    $course = trainingCourse($this);
    $outsiderUser = userWithRoles('employee');
    $outsider = Employee::factory()->placedIn($this->otherBranch)->create(['user_id' => $outsiderUser->id]);
    resolve(Enrol::class)->handle(Actor::user($this->trainer), $outsider, $course, EnrollmentSource::Assigned);
    resolve(Enrol::class)->handle(Actor::user($this->trainer), $this->learner, $course, EnrollmentSource::Assigned);

    // The branch manager sees their branch's people, not the other branch's.
    $this->actingAs($this->managerUser)->getJson(route('api.v1.training.record', $this->learner))->assertOk()->assertJsonCount(1, 'data.enrollments');
    $this->getJson(route('api.v1.training.record', $outsider))->assertNotFound();
    $this->actingAs($outsiderUser)->getJson(route('api.v1.training.record', $this->learner))->assertNotFound();

    // Trainers see everyone on the course; the branch manager can't open the trainer screens.
    $this->actingAs($this->trainer)->get(route('training.manage.courses.people', $course))
        ->assertInertia(fn (Assert $page): Assert => $page->has('enrollments.data', 2));
    $this->actingAs($this->managerUser)->get(route('training.manage.courses.people', $course))->assertForbidden();
    $this->actingAs($this->learnerUser)->post(route('training.manage.courses.assign', $course), ['employees' => [$outsider->ulid]])->assertForbidden();
});

it('serves the catalog and enrolment over the API', function (): void {
    $course = trainingCourse($this);
    $this->actingAs($this->learnerUser);

    $this->getJson(route('api.v1.training.courses.index'))->assertOk()->assertJsonPath('data.0.title.en', 'Food safety');
    $this->getJson(route('api.v1.training.courses.show', $course))->assertOk()->assertJsonCount(2, 'data.modules');
    $id = $this->postJson(route('api.v1.training.courses.enrol', $course), [], ['Idempotency-Key' => 'enrol-1'])->assertCreated()->json('data.id');
    $this->getJson(route('api.v1.training.enrollments.index'))->assertOk()->assertJsonPath('data.0.id', $id);
    $this->deleteJson(route('api.v1.training.enrollments.cancel', $id), [], ['Idempotency-Key' => 'leave-1'])->assertOk()->assertJsonPath('data.status', 'cancelled');
});
